<?php

namespace BlueprintExtensions\StateMachine\Generators;

use Blueprint\Contracts\PluginGenerator;
use Blueprint\Contracts\Plugin;
use Blueprint\Plugin\AbstractPluginGenerator;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class StateMachineGenerator extends AbstractPluginGenerator
{
    public function __construct(Filesystem $filesystem, ?Plugin $plugin = null)
    {
        parent::__construct($filesystem, $plugin);
        $this->setTypes(['state_machine']);
        $this->setPriority(100);
    }

    /**
     * Get the generator name.
     */
    public function getName(): string
    {
        return 'StateMachineGenerator';
    }

    /**
     * Check if this generator should run for the given tree.
     */
    public function shouldRun(Tree $tree): bool
    {
        $treeArray = $tree->toArray();
        return !empty($treeArray['state_machines'] ?? []);
    }

    /**
     * Generate state machine related files based on the parsed tree.
     *
     * @param Tree $tree The parsed tree containing state machine configuration
     * @return array The list of generated files
     */
    public function output(Tree $tree): array
    {
        $output = [];

        $treeArray = $tree->toArray();
        $stateMachinesData = $treeArray['state_machines'] ?? [];

        if (empty($stateMachinesData)) {
            return $output;
        }

        foreach ($stateMachinesData as $modelName => $stateMachineConfig) {
            // Generate state machine trait
            if ($this->getConfigValue('generate_trait', true)) {
                $output = array_merge($output, $this->generateStateMachineTrait($modelName, $stateMachineConfig));
            }

            // Generate state machine events
            if ($this->getConfigValue('generate_events', true)) {
                $output = array_merge($output, $this->generateStateMachineEvents($modelName, $stateMachineConfig));
            }

            // Generate state machine observers
            if ($this->getConfigValue('generate_observers', true)) {
                $output = array_merge($output, $this->generateStateMachineObserver($modelName, $stateMachineConfig));
            }

            // Generate state machine middleware
            if ($this->getConfigValue('generate_middleware', true)) {
                $output = array_merge($output, $this->generateStateMachineMiddleware($modelName, $stateMachineConfig));
            }

            // Generate state history migration and model
            if ($this->getConfigValue('track_state_history', true) && $stateMachineConfig['track_history']) {
                $output = array_merge($output, $this->generateStateHistoryFiles($modelName, $stateMachineConfig));
            }

            // Generate tests
            if ($this->getConfigValue('generate_tests', true)) {
                $output = array_merge($output, $this->generateStateMachineTests($modelName, $stateMachineConfig));
            }

            // Update the model to include state machine functionality
            $output = array_merge($output, $this->updateModelWithStateMachine($modelName, $stateMachineConfig));
        }

        return $output;
    }

    /**
     * Get configuration value with fallback.
     */
    protected function getConfigValue(string $key, $default = null)
    {
        // Try to get from generator config first (for testing)
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        // Try to get from config function if available
        if (function_exists('config')) {
            try {
                $value = config("blueprint-statemachine.{$key}", $default);
                if ($value !== $default) {
                    return $value;
                }
            } catch (\Exception $e) {
                // Config not available, fall back to default
            }
        }

        return $default;
    }

    /**
     * Generate state machine trait for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateMachineTrait(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $traitName = $modelName . 'StateMachine';
        $traitPath = $this->getConfigValue('paths.traits', 'app/StateMachine/Traits') . '/' . $traitName . '.php';

        $this->ensureDirectoryExists(dirname($traitPath));

        $traitContent = $this->generateTraitContent($modelName, $traitName, $stateMachineConfig);
        $this->filesystem->put($traitPath, $traitContent);
        $output[$traitPath] = 'created';

        return $output;
    }

    /**
     * Generate state machine events for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateMachineEvents(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $eventsPath = $this->getConfigValue('paths.events', 'app/StateMachine/Events');

        $this->ensureDirectoryExists($eventsPath);

        $transitions = $stateMachineConfig['transitions'] ?? [];

        foreach ($transitions as $transitionName => $transitionConfig) {
            $eventName = $modelName . Str::studly($transitionName);
            $eventPath = $eventsPath . '/' . $eventName . '.php';

            $eventContent = $this->generateEventContent($modelName, $eventName, $transitionName, $transitionConfig);
            $this->filesystem->put($eventPath, $eventContent);
            $output[$eventPath] = 'created';
        }

        return $output;
    }

    /**
     * Generate state machine observer for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateMachineObserver(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $observerName = $modelName . 'StateMachineObserver';
        $observerPath = $this->getConfigValue('paths.observers', 'app/StateMachine/Observers') . '/' . $observerName . '.php';

        $this->ensureDirectoryExists(dirname($observerPath));

        $observerContent = $this->generateObserverContent($modelName, $observerName, $stateMachineConfig);
        $this->filesystem->put($observerPath, $observerContent);
        $output[$observerPath] = 'created';

        return $output;
    }

    /**
     * Generate state machine middleware for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateMachineMiddleware(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $middlewareName = 'Ensure' . $modelName . 'State';
        $middlewarePath = $this->getConfigValue('paths.middleware', 'app/StateMachine/Middleware') . '/' . $middlewareName . '.php';

        $this->ensureDirectoryExists(dirname($middlewarePath));

        $middlewareContent = $this->generateMiddlewareContent($modelName, $middlewareName, $stateMachineConfig);
        $this->filesystem->put($middlewarePath, $middlewareContent);
        $output[$middlewarePath] = 'created';

        return $output;
    }

    /**
     * Generate state history files for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateHistoryFiles(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $tableName = Str::snake(Str::plural($modelName)) . '_state_history';
        $migrationName = date('Y_m_d_His') . '_create_' . $tableName . '_table.php';
        $migrationPath = 'database/migrations/' . $migrationName;

        // Generate migration
        $migrationContent = $this->generateStateHistoryMigration($modelName, $tableName, $stateMachineConfig);
        $this->filesystem->put($migrationPath, $migrationContent);
        $output[$migrationPath] = 'created';

        // Generate model
        $historyModelName = $modelName . 'StateHistory';
        $historyModelPath = 'app/Models/' . $historyModelName . '.php';
        $historyModelContent = $this->generateStateHistoryModel($modelName, $historyModelName, $stateMachineConfig);
        $this->filesystem->put($historyModelPath, $historyModelContent);
        $output[$historyModelPath] = 'created';

        return $output;
    }

    /**
     * Generate state machine tests for a model.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function generateStateMachineTests(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $testName = $modelName . 'StateMachineTest';
        $testPath = $this->getConfigValue('paths.tests', 'tests/Feature/StateMachine') . '/' . $testName . '.php';

        $this->ensureDirectoryExists(dirname($testPath));

        $testContent = $this->generateTestContent($modelName, $testName, $stateMachineConfig);
        $this->filesystem->put($testPath, $testContent);
        $output[$testPath] = 'created';

        return $output;
    }

    /**
     * Update the model to include state machine functionality.
     *
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return array Generated files
     */
    protected function updateModelWithStateMachine(string $modelName, array $stateMachineConfig): array
    {
        $output = [];
        $modelPath = 'app/Models/' . $modelName . '.php';

        if ($this->filesystem->exists($modelPath)) {
            $modelContent = $this->filesystem->get($modelPath);
            $modifiedContent = $this->addStateMachineToModel($modelContent, $modelName, $stateMachineConfig);

            if ($modifiedContent !== $modelContent) {
                $this->filesystem->put($modelPath, $modifiedContent);
                $output[$modelPath] = 'updated';
            }
        }

        return $output;
    }

    /**
     * Generate trait content for state machine functionality.
     *
     * @param string $modelName The model name
     * @param string $traitName The trait name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The trait content
     */
    protected function generateTraitContent(string $modelName, string $traitName, array $stateMachineConfig): string
    {
        $namespace = $this->getConfigValue('namespace', 'App\\StateMachine') . '\\Traits';
        $field = $stateMachineConfig['field'];
        $transitions = $stateMachineConfig['transitions'] ?? [];
        $guards = $stateMachineConfig['guards'] ?? [];
        $callbacks = $stateMachineConfig['callbacks'] ?? [];
        $states = $stateMachineConfig['states'] ?? [];

        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use Illuminate\\Database\\Eloquent\\Model;\n";
        $content .= "use Illuminate\\Support\\Facades\\Event;\n\n";
        $content .= "trait {$traitName}\n{\n";

        // Add state constants
        $content .= "    // State constants\n";
        foreach ($states as $stateName => $stateConfig) {
            $constantName = 'STATE_' . strtoupper($stateName);
            $content .= "    public const {$constantName} = '{$stateName}';\n";
        }
        $content .= "\n";

        // Add transition methods
        foreach ($transitions as $transitionName => $transitionConfig) {
            $content .= $this->generateTransitionMethod($transitionName, $transitionConfig, $field, $guards, $callbacks, $modelName);
        }

        // Add utility methods
        $content .= $this->generateUtilityMethods($field, $states, $transitions);

        $content .= "}\n";

        return $content;
    }

    /**
     * Generate transition method for the trait.
     *
     * @param string $transitionName The transition name
     * @param array $transitionConfig The transition configuration
     * @param string $field The state field name
     * @param array $guards The guards configuration
     * @param array $callbacks The callbacks configuration
     * @param string $modelName The model name
     * @return string The transition method content
     */
    protected function generateTransitionMethod(string $transitionName, array $transitionConfig, string $field, array $guards, array $callbacks, string $modelName): string
    {
        $methodName = Str::camel($transitionName);
        $toState = $transitionConfig['to'];
        $fromStates = $transitionConfig['from'];
        $guardMethod = $guards[$transitionName]['method'] ?? null;
        $beforeCallback = $callbacks[$transitionName]['before']['method'] ?? null;
        $afterCallback = $callbacks[$transitionName]['after']['method'] ?? null;

        $content = "    /**\n";
        $content .= "     * Transition: {$transitionName}\n";
        $content .= "     * From: " . implode(', ', $fromStates) . "\n";
        $content .= "     * To: {$toState}\n";
        $content .= "     */\n";
        $content .= "    public function {$methodName}(): bool\n";
        $content .= "    {\n";

        // Check if transition is valid
        $content .= "        if (!in_array(\$this->{$field}, ['" . implode("', '", $fromStates) . "'])) {\n";
        $content .= "            return false;\n";
        $content .= "        }\n\n";

        // Check guard
        if ($guardMethod) {
            $content .= "        if (!\$this->{$guardMethod}()) {\n";
            $content .= "            return false;\n";
            $content .= "        }\n\n";
        }

        // Before callback
        if ($beforeCallback) {
            $content .= "        \$this->{$beforeCallback}();\n\n";
        }

        // Fire before event
        $content .= "        Event::dispatch(new \\App\\StateMachine\\Events\\{$modelName}" . Str::studly($transitionName) . "(\$this, 'before'));\n\n";

        // Update state
        $content .= "        \$oldState = \$this->{$field};\n";
        $content .= "        \$this->{$field} = '{$toState}';\n";
        $content .= "        \$this->save();\n\n";

        // Track history
        $content .= "        if (config('blueprint-statemachine.track_state_history', true)) {\n";
        $content .= "            \$this->recordStateTransition(\$oldState, '{$toState}', '{$transitionName}');\n";
        $content .= "        }\n\n";

        // After callback
        if ($afterCallback) {
            $content .= "        \$this->{$afterCallback}();\n\n";
        }

        // Fire after event
        $content .= "        Event::dispatch(new \\App\\StateMachine\\Events\\{$modelName}" . Str::studly($transitionName) . "(\$this, 'after'));\n\n";

        $content .= "        return true;\n";
        $content .= "    }\n\n";

        return $content;
    }

    /**
     * Generate utility methods for the trait.
     *
     * @param string $field The state field name
     * @param array $states The states configuration
     * @param array $transitions The transitions configuration
     * @return string The utility methods content
     */
    protected function generateUtilityMethods(string $field, array $states, array $transitions): string
    {
        $content = "    /**\n";
        $content .= "     * Get all available states.\n";
        $content .= "     */\n";
        $content .= "    public static function getStates(): array\n";
        $content .= "    {\n";
        $content .= "        return [\n";
        foreach ($states as $stateName => $stateConfig) {
            $constantName = 'self::STATE_' . strtoupper($stateName);
            $content .= "            {$constantName} => '{$stateConfig['label']}',\n";
        }
        $content .= "        ];\n";
        $content .= "    }\n\n";

        $content .= "    /**\n";
        $content .= "     * Get available transitions for current state.\n";
        $content .= "     */\n";
        $content .= "    public function getAvailableTransitions(): array\n";
        $content .= "    {\n";
        $content .= "        \$currentState = \$this->{$field};\n";
        $content .= "        \$transitions = [];\n\n";
        foreach ($transitions as $transitionName => $transitionConfig) {
            $fromStates = $transitionConfig['from'];
            $content .= "        if (in_array(\$currentState, ['" . implode("', '", $fromStates) . "'])) {\n";
            $content .= "            \$transitions[] = '{$transitionName}';\n";
            $content .= "        }\n";
        }
        $content .= "\n        return \$transitions;\n";
        $content .= "    }\n\n";

        $content .= "    /**\n";
        $content .= "     * Check if a transition is available.\n";
        $content .= "     */\n";
        $content .= "    public function canTransition(string \$transition): bool\n";
        $content .= "    {\n";
        $content .= "        return in_array(\$transition, \$this->getAvailableTransitions());\n";
        $content .= "    }\n\n";

        // Add state check methods
        foreach ($states as $stateName => $stateConfig) {
            $methodName = 'is' . Str::studly($stateName);
            $content .= "    /**\n";
            $content .= "     * Check if the model is in {$stateName} state.\n";
            $content .= "     */\n";
            $content .= "    public function {$methodName}(): bool\n";
            $content .= "    {\n";
            $content .= "        return \$this->{$field} === '{$stateName}';\n";
            $content .= "    }\n\n";
        }

        // Add scopes
        foreach ($states as $stateName => $stateConfig) {
            $scopeName = Str::studly($stateName);
            $content .= "    /**\n";
            $content .= "     * Scope to filter models in {$stateName} state.\n";
            $content .= "     */\n";
            $content .= "    public function scope{$scopeName}(\$query)\n";
            $content .= "    {\n";
            $content .= "        return \$query->where('{$field}', '{$stateName}');\n";
            $content .= "    }\n\n";
        }

        $content .= "    /**\n";
        $content .= "     * Record state transition in history.\n";
        $content .= "     */\n";
        $content .= "    protected function recordStateTransition(string \$fromState, string \$toState, string \$transition): void\n";
        $content .= "    {\n";
        $content .= "        \$this->stateHistory()->create([\n";
        $content .= "            'from_state' => \$fromState,\n";
        $content .= "            'to_state' => \$toState,\n";
        $content .= "            'transition' => \$transition,\n";
        $content .= "            'created_at' => now(),\n";
        $content .= "        ]);\n";
        $content .= "    }\n\n";

        $content .= "    /**\n";
        $content .= "     * Relationship to state history.\n";
        $content .= "     */\n";
        $content .= "    public function stateHistory()\n";
        $content .= "    {\n";
        $content .= "        return \$this->hasMany(\\App\\Models\\{\$this->getTable()}StateHistory::class);\n";
        $content .= "    }\n\n";

        return $content;
    }

    /**
     * Generate event content.
     *
     * @param string $modelName The model name
     * @param string $eventName The event name
     * @param string $transitionName The transition name
     * @param array $transitionConfig The transition configuration
     * @return string The event content
     */
    protected function generateEventContent(string $modelName, string $eventName, string $transitionName, array $transitionConfig): string
    {
        $namespace = $this->getConfigValue('namespace', 'App\\StateMachine') . '\\Events';
        
        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use Illuminate\\Foundation\\Events\\Dispatchable;\n";
        $content .= "use Illuminate\\Queue\\SerializesModels;\n";
        $content .= "use App\\Models\\{$modelName};\n\n";
        $content .= "class {$eventName}\n{\n";
        $content .= "    use Dispatchable, SerializesModels;\n\n";
        $content .= "    public {$modelName} \$model;\n";
        $content .= "    public string \$phase;\n\n";
        $content .= "    /**\n";
        $content .= "     * Create a new event instance.\n";
        $content .= "     */\n";
        $content .= "    public function __construct({$modelName} \$model, string \$phase = 'after')\n";
        $content .= "    {\n";
        $content .= "        \$this->model = \$model;\n";
        $content .= "        \$this->phase = \$phase;\n";
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Generate observer content.
     *
     * @param string $modelName The model name
     * @param string $observerName The observer name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The observer content
     */
    protected function generateObserverContent(string $modelName, string $observerName, array $stateMachineConfig): string
    {
        $namespace = $this->getConfigValue('namespace', 'App\\StateMachine') . '\\Observers';
        
        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use App\\Models\\{$modelName};\n\n";
        $content .= "class {$observerName}\n{\n";
        $content .= "    /**\n";
        $content .= "     * Handle the {$modelName} \"creating\" event.\n";
        $content .= "     */\n";
        $content .= "    public function creating({$modelName} \$model): void\n";
        $content .= "    {\n";
        if ($stateMachineConfig['initial']) {
            $content .= "        if (empty(\$model->{$stateMachineConfig['field']})) {\n";
            $content .= "            \$model->{$stateMachineConfig['field']} = '{$stateMachineConfig['initial']}';\n";
            $content .= "        }\n";
        }
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Generate middleware content.
     *
     * @param string $modelName The model name
     * @param string $middlewareName The middleware name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The middleware content
     */
    protected function generateMiddlewareContent(string $modelName, string $middlewareName, array $stateMachineConfig): string
    {
        $namespace = $this->getConfigValue('namespace', 'App\\StateMachine') . '\\Middleware';
        
        $content = "<?php\n\nnamespace {$namespace};\n\n";
        $content .= "use Closure;\n";
        $content .= "use Illuminate\\Http\\Request;\n";
        $content .= "use App\\Models\\{$modelName};\n\n";
        $content .= "class {$middlewareName}\n{\n";
        $content .= "    /**\n";
        $content .= "     * Handle an incoming request.\n";
        $content .= "     */\n";
        $content .= "    public function handle(Request \$request, Closure \$next, string \$state = null, string \$parameter = 'id'): mixed\n";
        $content .= "    {\n";
        $content .= "        if (\$state) {\n";
        $content .= "            \$model = {$modelName}::findOrFail(\$request->route(\$parameter));\n";
        $content .= "            \n";
        $content .= "            if (\$model->{$stateMachineConfig['field']} !== \$state) {\n";
        $content .= "                abort(403, 'Access denied. Resource is not in the required state.');\n";
        $content .= "            }\n";
        $content .= "        }\n\n";
        $content .= "        return \$next(\$request);\n";
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Generate state history migration.
     *
     * @param string $modelName The model name
     * @param string $tableName The table name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The migration content
     */
    protected function generateStateHistoryMigration(string $modelName, string $tableName, array $stateMachineConfig): string
    {
        $className = 'Create' . Str::studly($tableName) . 'Table';
        $modelTable = Str::snake(Str::plural($modelName));
        
        $content = "<?php\n\n";
        $content .= "use Illuminate\\Database\\Migrations\\Migration;\n";
        $content .= "use Illuminate\\Database\\Schema\\Blueprint;\n";
        $content .= "use Illuminate\\Support\\Facades\\Schema;\n\n";
        $content .= "return new class extends Migration\n{\n";
        $content .= "    /**\n";
        $content .= "     * Run the migrations.\n";
        $content .= "     */\n";
        $content .= "    public function up(): void\n";
        $content .= "    {\n";
        $content .= "        Schema::create('{$tableName}', function (Blueprint \$table) {\n";
        $content .= "            \$table->id();\n";
        $content .= "            \$table->foreignId('" . Str::snake($modelName) . "_id')->constrained('{$modelTable}')->onDelete('cascade');\n";
        $content .= "            \$table->string('from_state');\n";
        $content .= "            \$table->string('to_state');\n";
        $content .= "            \$table->string('transition');\n";
        $content .= "            \$table->json('metadata')->nullable();\n";
        $content .= "            \$table->timestamp('created_at');\n";
        $content .= "            \n";
        $content .= "            \$table->index(['" . Str::snake($modelName) . "_id', 'created_at']);\n";
        $content .= "        });\n";
        $content .= "    }\n\n";
        $content .= "    /**\n";
        $content .= "     * Reverse the migrations.\n";
        $content .= "     */\n";
        $content .= "    public function down(): void\n";
        $content .= "    {\n";
        $content .= "        Schema::dropIfExists('{$tableName}');\n";
        $content .= "    }\n";
        $content .= "};\n";

        return $content;
    }

    /**
     * Generate state history model.
     *
     * @param string $modelName The model name
     * @param string $historyModelName The history model name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The model content
     */
    protected function generateStateHistoryModel(string $modelName, string $historyModelName, array $stateMachineConfig): string
    {
        $content = "<?php\n\nnamespace App\\Models;\n\n";
        $content .= "use Illuminate\\Database\\Eloquent\\Model;\n";
        $content .= "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\n\n";
        $content .= "class {$historyModelName} extends Model\n{\n";
        $content .= "    public \$timestamps = false;\n\n";
        $content .= "    protected \$fillable = [\n";
        $content .= "        'from_state',\n";
        $content .= "        'to_state',\n";
        $content .= "        'transition',\n";
        $content .= "        'metadata',\n";
        $content .= "        'created_at',\n";
        $content .= "    ];\n\n";
        $content .= "    protected \$casts = [\n";
        $content .= "        'metadata' => 'array',\n";
        $content .= "        'created_at' => 'datetime',\n";
        $content .= "    ];\n\n";
        $content .= "    /**\n";
        $content .= "     * Get the model that owns the state history.\n";
        $content .= "     */\n";
        $content .= "    public function " . Str::camel($modelName) . "(): BelongsTo\n";
        $content .= "    {\n";
        $content .= "        return \$this->belongsTo({$modelName}::class);\n";
        $content .= "    }\n";
        $content .= "}\n";

        return $content;
    }

    /**
     * Generate test content.
     *
     * @param string $modelName The model name
     * @param string $testName The test name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The test content
     */
    protected function generateTestContent(string $modelName, string $testName, array $stateMachineConfig): string
    {
        $content = "<?php\n\nnamespace Tests\\Feature\\StateMachine;\n\n";
        $content .= "use Tests\\TestCase;\n";
        $content .= "use App\\Models\\{$modelName};\n";
        $content .= "use Illuminate\\Foundation\\Testing\\RefreshDatabase;\n\n";
        $content .= "class {$testName} extends TestCase\n{\n";
        $content .= "    use RefreshDatabase;\n\n";

        $transitions = $stateMachineConfig['transitions'] ?? [];
        foreach ($transitions as $transitionName => $transitionConfig) {
            $methodName = 'test_' . Str::snake($transitionName) . '_transition';
            $content .= "    /**\n";
            $content .= "     * Test {$transitionName} transition.\n";
            $content .= "     */\n";
            $content .= "    public function {$methodName}(): void\n";
            $content .= "    {\n";
            $content .= "        \$model = {$modelName}::factory()->create([\n";
            $content .= "            '{$stateMachineConfig['field']}' => '{$transitionConfig['from'][0]}',\n";
            $content .= "        ]);\n\n";
            $content .= "        \$result = \$model->" . Str::camel($transitionName) . "();\n\n";
            $content .= "        \$this->assertTrue(\$result);\n";
            $content .= "        \$this->assertEquals('{$transitionConfig['to']}', \$model->{$stateMachineConfig['field']});\n";
            $content .= "    }\n\n";
        }

        $content .= "}\n";

        return $content;
    }

    /**
     * Add state machine functionality to existing model.
     *
     * @param string $modelContent The current model content
     * @param string $modelName The model name
     * @param array $stateMachineConfig The state machine configuration
     * @return string The modified model content
     */
    protected function addStateMachineToModel(string $modelContent, string $modelName, array $stateMachineConfig): string
    {
        $traitName = $modelName . 'StateMachine';
        $traitUse = "use App\\StateMachine\\Traits\\{$traitName};";

        // Check if trait is already used
        if (str_contains($modelContent, $traitUse)) {
            return $modelContent;
        }

        // Add trait use statement
        if (preg_match('/^class\s+' . preg_quote($modelName) . '\s+extends\s+[^\{]+\{/m', $modelContent, $matches, PREG_OFFSET_CAPTURE)) {
            $classStart = $matches[0][1] + strlen($matches[0][0]);
            $beforeClass = substr($modelContent, 0, $classStart);
            $afterClass = substr($modelContent, $classStart);

            // Add trait usage
            $traitUsage = "\n    use {$traitName};\n";
            $modifiedContent = $beforeClass . $traitUsage . $afterClass;

            return $modifiedContent;
        }

        return $modelContent;
    }

    /**
     * Ensure directory exists.
     *
     * @param string $directory The directory path
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
    }
} 
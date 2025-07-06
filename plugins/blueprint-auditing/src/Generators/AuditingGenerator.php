<?php

namespace BlueprintExtensions\Auditing\Generators;

use Blueprint\Contracts\PluginGenerator;
use Blueprint\Contracts\Plugin;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class AuditingGenerator implements PluginGenerator
{
    /** @var Filesystem */
    protected $filesystem;

    /** @var Plugin */
    protected $plugin;

    /** @var array */
    protected $config = [];

    /** @var int */
    protected $priority = 100;

    /** @var array */
    protected $types = ['auditing'];

    public function __construct(Filesystem $filesystem, ?Plugin $plugin = null)
    {
        $this->filesystem = $filesystem;
        $this->plugin = $plugin;
    }

    /**
     * Get the plugin that provides this generator.
     */
    public function getPlugin(): Plugin
    {
        if ($this->plugin === null) {
            throw new \RuntimeException('Plugin not set for this generator');
        }
        return $this->plugin;
    }

    /**
     * Set the plugin for this generator.
     */
    public function setPlugin(Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }

    /**
     * Get the generator name.
     */
    public function getName(): string
    {
        return 'AuditingGenerator';
    }

    /**
     * Get the generator priority (higher = runs first).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the generator priority.
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Check if this generator should run for the given tree.
     */
    public function shouldRun(Tree $tree): bool
    {
        $treeArray = $tree->toArray();
        return !empty($treeArray['auditing'] ?? []);
    }

    /**
     * Get the generator configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the generator configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Check if this generator can handle the given type.
     */
    public function canHandle(string $type): bool
    {
        return in_array($type, $this->types());
    }

    /**
     * Get the types this generator handles.
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * Set the types this generator handles.
     */
    protected function setTypes(array $types): void
    {
        $this->types = $types;
    }

    /**
     * Get the generator description.
     */
    public function getDescription(): string
    {
        if ($this->plugin === null) {
            return 'Auditing generator';
        }
        return 'Plugin generator provided by ' . $this->plugin->getName();
    }

    /**
     * Generate auditing-related files based on the parsed tree.
     *
     * @param Tree $tree The parsed tree containing auditing configuration
     * @return array The list of generated files
     */
    public function output(Tree $tree): array
    {
        $output = [];

        $treeArray = $tree->toArray();
        $auditingData = $treeArray['auditing'] ?? [];

        if (empty($auditingData)) {
            return $output;
        }

        foreach ($auditingData as $modelName => $auditingConfig) {
            if ($auditingConfig['enabled']) {
                // Generate model trait and configuration
                if ($this->getConfigValue('generate_auditing', true)) {
                    $output = array_merge($output, $this->generateModelAuditing($modelName, $auditingConfig, $tree));
                }

                // Generate migration for audits table if needed
                if ($this->getConfigValue('generate_migrations', true) && $this->shouldGenerateAuditsMigration()) {
                    $output = array_merge($output, $this->generateAuditsMigration());
                }

                // Generate custom audit model if specified
                if ($this->getConfigValue('generate_custom_models', true) && isset($auditingConfig['implementation']) && $auditingConfig['implementation'] !== 'OwenIt\\Auditing\\Models\\Audit') {
                    $output = array_merge($output, $this->generateCustomAuditModel($auditingConfig['implementation']));
                }
            }
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
                $value = config("blueprint-auditing.{$key}", $default);
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
     * Generate auditing configuration for a model.
     *
     * @param string $modelName The model name
     * @param array $auditingConfig The auditing configuration
     * @param Tree $tree The full tree
     * @return array Generated files
     */
    protected function generateModelAuditing(string $modelName, array $auditingConfig, Tree $tree): array
    {
        $output = [];
        $modelPath = $this->getModelPath($modelName);

        if (!$this->filesystem->exists($modelPath)) {
            // If model doesn't exist, we can't modify it
            return $output;
        }

        $modelContent = $this->filesystem->get($modelPath);
        $modifiedContent = $this->addAuditingToModel($modelContent, $modelName, $auditingConfig);

        if ($modifiedContent !== $modelContent) {
            $this->filesystem->put($modelPath, $modifiedContent);
            $output[$modelPath] = 'updated';
        }

        return $output;
    }

    /**
     * Add auditing configuration to a model.
     *
     * @param string $content The model file content
     * @param string $modelName The model name
     * @param array $config The auditing configuration
     * @return string The modified content
     */
    protected function addAuditingToModel(string $content, string $modelName, array $config): string
    {
        // Add use statements after namespace
        $useStatements = [];
        if (!str_contains($content, 'use OwenIt\\Auditing\\Contracts\\Auditable;')) {
            $useStatements[] = 'use OwenIt\\Auditing\\Contracts\\Auditable;';
            $useStatements[] = 'use OwenIt\\Auditing\\Auditable as AuditableTrait;';
        }

        // Add rewind trait if rewind is enabled
        if (isset($config['rewind']) && $config['rewind']['enabled']) {
            if (!str_contains($content, 'use BlueprintExtensions\\Auditing\\Traits\\RewindableTrait;')) {
                $useStatements[] = 'use BlueprintExtensions\\Auditing\\Traits\\RewindableTrait;';
            }
        }

        // Add use statements
        if (!empty($useStatements)) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;\s*\n)/',
                "$1\n" . implode("\n", $useStatements) . "\n",
                $content
            );
        }

        // Add interface implementation
        if (!str_contains($content, 'implements Auditable')) {
            $content = preg_replace(
                '/class\s+' . $modelName . '(\s+extends\s+[\w\\\\]+)?/',
                'class ' . $modelName . '$1 implements Auditable',
                $content
            );
        }

        // Add trait usage
        $traits = ['AuditableTrait'];
        if (isset($config['rewind']) && $config['rewind']['enabled']) {
            $traits[] = 'RewindableTrait';
        }

        foreach ($traits as $trait) {
            if (!str_contains($content, "use {$trait};")) {
                $content = preg_replace(
                    '/(class\s+' . $modelName . '.*?\{[^}]*?use\s+[^;]+;)/s',
                    "$1\n    use {$trait};",
                    $content
                );
                
                // If no existing traits, add after class opening
                if (!str_contains($content, "use {$trait};")) {
                    $content = preg_replace(
                        '/(class\s+' . $modelName . '.*?\{\s*)/s',
                        "$1\n    use {$trait};\n",
                        $content
                    );
                }
            }
        }

        // Add auditing configuration properties
        $properties = $this->generateAuditingProperties($config);
        if ($properties) {
            // Find the position after trait usage or class opening
            $insertPosition = $this->findInsertPosition($content);
            
            if ($insertPosition !== false) {
                $content = substr_replace($content, $properties, $insertPosition, 0);
            }
        }

        return $content;
    }

    /**
     * Generate auditing configuration properties.
     *
     * @param array $config The auditing configuration
     * @return string The properties as PHP code
     */
    protected function generateAuditingProperties(array $config): string
    {
        $properties = [];

        if (isset($config['events'])) {
            $events = array_map(function ($event) {
                return "'{$event}'";
            }, $config['events']);
            $properties[] = "    protected \$auditEvents = [" . implode(', ', $events) . "];";
        }

        if (isset($config['exclude'])) {
            $exclude = array_map(function ($attr) {
                return "'{$attr}'";
            }, $config['exclude']);
            $properties[] = "    protected \$auditExclude = [" . implode(', ', $exclude) . "];";
        }

        if (isset($config['include'])) {
            $include = array_map(function ($attr) {
                return "'{$attr}'";
            }, $config['include']);
            $properties[] = "    protected \$auditInclude = [" . implode(', ', $include) . "];";
        }

        if (isset($config['strict'])) {
            $properties[] = "    protected \$auditStrict = " . ($config['strict'] ? 'true' : 'false') . ";";
        }

        if (isset($config['threshold'])) {
            $properties[] = "    protected \$auditThreshold = {$config['threshold']};";
        }

        if (isset($config['console'])) {
            $properties[] = "    protected \$auditConsole = " . ($config['console'] ? 'true' : 'false') . ";";
        }

        if (isset($config['empty_values'])) {
            $properties[] = "    protected \$auditEmptyValues = " . ($config['empty_values'] ? 'true' : 'false') . ";";
        }

        if (isset($config['user'])) {
            $properties[] = "    protected \$auditUser = '{$config['user']}';";
        }

        if (isset($config['implementation'])) {
            $properties[] = "    protected \$auditImplementation = '{$config['implementation']}';";
        }

        if (isset($config['resolvers'])) {
            $resolvers = [];
            foreach ($config['resolvers'] as $key => $value) {
                $resolvers[] = "        '{$key}' => '{$value}'";
            }
            $properties[] = "    protected \$auditResolvers = [\n" . implode(",\n", $resolvers) . "\n    ];";
        }

        if (isset($config['tags'])) {
            $tags = array_map(function ($tag) {
                return "'{$tag}'";
            }, $config['tags']);
            $properties[] = "    protected \$auditTags = [" . implode(', ', $tags) . "];";
        }

        if (isset($config['transformations'])) {
            $transformations = [];
            foreach ($config['transformations'] as $key => $value) {
                $transformations[] = "        '{$key}' => '{$value}'";
            }
            $properties[] = "    protected \$auditTransformations = [\n" . implode(",\n", $transformations) . "\n    ];";
        }

        if (isset($config['audit_attach'])) {
            $properties[] = "    protected \$auditAttach = " . ($config['audit_attach'] ? 'true' : 'false') . ";";
        }

        if (isset($config['audit_detach'])) {
            $properties[] = "    protected \$auditDetach = " . ($config['audit_detach'] ? 'true' : 'false') . ";";
        }

        if (isset($config['audit_sync'])) {
            $properties[] = "    protected \$auditSync = " . ($config['audit_sync'] ? 'true' : 'false') . ";";
        }

        // Add rewind properties if rewind is enabled
        if (isset($config['rewind']) && $config['rewind']['enabled']) {
            $rewindProperties = $this->generateRewindProperties($config['rewind']);
            if ($rewindProperties) {
                $properties[] = $rewindProperties;
            }
        }

        if (empty($properties)) {
            return '';
        }

        return "\n    // Auditing Configuration\n" . implode("\n", $properties) . "\n";
    }

    /**
     * Generate rewind configuration properties.
     *
     * @param array $rewindConfig The rewind configuration
     * @return string The rewind properties as PHP code
     */
    protected function generateRewindProperties(array $rewindConfig): string
    {
        $properties = [];

        if (isset($rewindConfig['enabled'])) {
            $properties[] = "    protected \$rewindEnabled = " . ($rewindConfig['enabled'] ? 'true' : 'false') . ";";
        }

        if (isset($rewindConfig['methods'])) {
            $methods = array_map(function ($method) {
                return "'{$method}'";
            }, $rewindConfig['methods']);
            $properties[] = "    protected \$rewindMethods = [" . implode(', ', $methods) . "];";
        }

        if (isset($rewindConfig['validate'])) {
            $properties[] = "    protected \$rewindValidate = " . ($rewindConfig['validate'] ? 'true' : 'false') . ";";
        }

        if (isset($rewindConfig['events'])) {
            $events = array_map(function ($event) {
                return "'{$event}'";
            }, $rewindConfig['events']);
            $properties[] = "    protected \$rewindEvents = [" . implode(', ', $events) . "];";
        }

        if (isset($rewindConfig['backup'])) {
            $properties[] = "    protected \$rewindBackup = " . ($rewindConfig['backup'] ? 'true' : 'false') . ";";
        }

        if (isset($rewindConfig['max_steps'])) {
            $properties[] = "    protected \$rewindMaxSteps = {$rewindConfig['max_steps']};";
        }

        if (isset($rewindConfig['include_attributes'])) {
            $attributes = array_map(function ($attr) {
                return "'{$attr}'";
            }, $rewindConfig['include_attributes']);
            $properties[] = "    protected \$rewindIncludeAttributes = [" . implode(', ', $attributes) . "];";
        }

        if (isset($rewindConfig['exclude_attributes'])) {
            $attributes = array_map(function ($attr) {
                return "'{$attr}'";
            }, $rewindConfig['exclude_attributes']);
            $properties[] = "    protected \$rewindExcludeAttributes = [" . implode(', ', $attributes) . "];";
        }

        if (empty($properties)) {
            return '';
        }

        return "\n    // Rewind Configuration\n" . implode("\n", $properties);
    }

    /**
     * Find the position to insert auditing properties in the model.
     *
     * @param string $content The model content
     * @return int|false The position to insert or false if not found
     */
    protected function findInsertPosition(string $content)
    {
        // Try to find after trait usage
        if (preg_match('/(use\s+[^;]+;\s*)\n/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches);
            return $lastMatch[1] + strlen($lastMatch[0]);
        }

        // Try to find after class opening
        if (preg_match('/(class\s+[\w]+\s*\{[^}]*?)\n/s', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $matches[1][1] + strlen($matches[1][0]);
        }

        return false;
    }

    /**
     * Check if we should generate the audits migration.
     *
     * @return bool
     */
    protected function shouldGenerateAuditsMigration(): bool
    {
        $migrationPath = 'database/migrations';
        $files = $this->filesystem->files($migrationPath);
        
        foreach ($files as $file) {
            if (str_contains($file, 'create_audits_table')) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Generate the audits migration.
     *
     * @return array Generated files
     */
    protected function generateAuditsMigration(): array
    {
        $output = [];
        $migrationName = date('Y_m_d_His') . '_create_audits_table.php';
        $migrationPath = 'database/migrations/' . $migrationName;

        $migrationContent = $this->generateAuditsMigrationContent();
        $this->filesystem->put($migrationPath, $migrationContent);
        $output[$migrationPath] = 'created';

        return $output;
    }

    /**
     * Generate the audits migration content.
     *
     * @return string The migration content
     */
    protected function generateAuditsMigrationContent(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
PHP;
    }

    /**
     * Generate a custom audit model.
     *
     * @param string $modelClass The model class name
     * @return array Generated files
     */
    protected function generateCustomAuditModel(string $modelClass): array
    {
        $output = [];
        
        // Parse the class name from the full class path
        $parts = explode('\\', $modelClass);
        $className = end($parts);
        $namespace = implode('\\', array_slice($parts, 0, -1));
        
        $modelPath = 'app/Models/' . $className . '.php';
        
        if (!$this->filesystem->exists($modelPath)) {
            $modelContent = $this->generateCustomAuditModelContent($namespace, $className);
            $this->filesystem->put($modelPath, $modelContent);
            $output[$modelPath] = 'created';
        }

        return $output;
    }

    /**
     * Generate custom audit model content.
     *
     * @param string $namespace The namespace
     * @param string $className The class name
     * @return string The model content
     */
    protected function generateCustomAuditModelContent(string $namespace, string $className): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use OwenIt\Auditing\Models\Audit as BaseAudit;

class {$className} extends BaseAudit
{
    // Custom audit model implementation
    // Add your custom methods and properties here
}
PHP;
    }

    /**
     * Get the model path for a given model name.
     *
     * @param string $modelName The model name
     * @return string The model path
     */
    protected function getModelPath(string $modelName): string
    {
        return 'app/Models/' . $modelName . '.php';
    }
} 
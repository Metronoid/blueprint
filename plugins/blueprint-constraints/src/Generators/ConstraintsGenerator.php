<?php

namespace BlueprintExtensions\Constraints\Generators;

use Blueprint\Contracts\PluginGenerator;
use Blueprint\Contracts\Plugin;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Blueprint\Concerns\ManagesOutput;
use Blueprint\Services\DatabaseSchemaService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class ConstraintsGenerator implements PluginGenerator
{
    use ManagesOutput;

    /** @var Filesystem */
    protected $filesystem;

    /** @var Plugin */
    protected $plugin;

    /** @var array */
    protected $config = [];

    /** @var DatabaseSchemaService */
    protected $schemaService;

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
        return $this->plugin;
    }

    /**
     * Get the generator name.
     */
    public function getName(): string
    {
        return 'ConstraintsGenerator';
    }

    /**
     * Get the generator priority (higher = runs first).
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Check if this generator should run for the given tree.
     */
    public function shouldRun(Tree $tree): bool
    {
        $treeArray = $tree->toArray();
        return !empty($treeArray['constraints'] ?? []);
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
     * The types of files this generator creates.
     *
     * @return array
     */
    public function types(): array
    {
        return ['constraints'];
    }

    /**
     * Generate constraint-related files based on the parsed tree.
     *
     * @param Tree $tree The parsed tree containing constraints configuration
     * @return array The list of generated files
     */
    public function output(Tree $tree): array
    {
        $this->resetOutput();

        $treeArray = $tree->toArray();
        $constraintsData = $treeArray['constraints'] ?? [];

        if (empty($constraintsData)) {
            return $this->getOutput();
        }

        foreach ($constraintsData as $modelName => $constraintsConfig) {
            // Generate database constraints in migrations
            if ($this->getConfigValue('generate_database_constraints', true)) {
                $this->mergeOutput($this->generateDatabaseConstraints($modelName, $constraintsConfig));
            }

            // Generate validation rules in Form Requests
            if ($this->getConfigValue('generate_validation_rules', true)) {
                $this->mergeOutput($this->generateValidationRules($modelName, $constraintsConfig));
            }

            // Generate model mutators (optional)
            if ($this->getConfigValue('generate_model_mutators', false)) {
                $this->mergeOutput($this->generateModelMutators($modelName, $constraintsConfig));
            }
        }

        return $this->getOutput();
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
                $value = config("blueprint-constraints.{$key}", $default);
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
     * Generate database constraints for a model.
     *
     * @param string $modelName The model name
     * @param array $constraintsConfig The constraints configuration
     * @return array Generated files
     */
    protected function generateDatabaseConstraints(string $modelName, array $constraintsConfig): array
    {
        $output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];
        
        // Check if database constraints generation is enabled
        if (!$this->getConfigValue('generate_database_constraints', true)) {
            return $output;
        }
        
        $tableName = Str::snake(Str::plural($modelName));
        
        // Generate constraint migration with a timestamp that ensures it runs after table creation
        // Use current time + 60 seconds to ensure it runs after all table creation migrations
        $timestamp = now()->addSeconds(60)->format('Y_m_d_His');
        $migrationName = $timestamp . '_add_constraints_to_' . $tableName . '_table.php';
        $migrationPath = 'database/migrations/' . $migrationName;

        $constraints = $this->buildDatabaseConstraints($tableName, $constraintsConfig);

        if (!empty($constraints)) {
            $migrationContent = $this->generateConstraintsMigration($tableName, $constraints);
            $this->filesystem->put($migrationPath, $migrationContent);
            $output['created'][] = $migrationPath;
        }

        return $output;
    }

    /**
     * Generate validation rules for Form Requests.
     *
     * @param string $modelName The model name
     * @param array $constraintsConfig The constraints configuration
     * @return array Generated files
     */
    protected function generateValidationRules(string $modelName, array $constraintsConfig): array
    {
        $output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];
        
        // Check if validation rules generation is enabled
        if (!$this->getConfigValue('generate_validation_rules', true)) {
            return $output;
        }
        
        // Find existing Form Request files for this model
        $requestFiles = $this->findFormRequestFiles($modelName);
        
        foreach ($requestFiles as $requestFile) {
            $updatedContent = $this->addValidationRulesToRequest($requestFile, $constraintsConfig);
            if ($updatedContent) {
                $this->filesystem->put($requestFile, $updatedContent);
                $output['updated'][] = $requestFile;
            }
        }

        // If no existing requests, create a base validation rules file
        if (empty($requestFiles)) {
            $rulesFile = $this->createValidationRulesFile($modelName, $constraintsConfig);
            if ($rulesFile) {
                $output['created'][] = $rulesFile['path'];
            }
        }

        return $output;
    }

    /**
     * Generate model mutators for constraints.
     *
     * @param string $modelName The model name
     * @param array $constraintsConfig The constraints configuration
     * @return array Generated files
     */
        protected function generateModelMutators(string $modelName, array $constraintsConfig): array
    {
        $output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];
        
        // Check if model mutators generation is enabled
        if (!$this->getConfigValue('generate_model_mutators', false)) {
            return $output;
        }
        
        $modelPath = 'app/Models/' . $modelName . '.php';

        if ($this->filesystem->exists($modelPath)) {
            $modelContent = $this->filesystem->get($modelPath);
            $modifiedContent = $this->addMutatorsToModel($modelContent, $modelName, $constraintsConfig);

            if ($modifiedContent !== $modelContent) {
                $this->filesystem->put($modelPath, $modifiedContent);
                $output['updated'][] = $modelPath;
            }
        }

        return $output;
    }

    /**
     * Build database constraints from configuration.
     *
     * @param string $tableName The table name
     * @param array $constraintsConfig The constraints configuration
     * @return array The database constraints
     */
    protected function buildDatabaseConstraints(string $tableName, array $constraintsConfig): array
    {
        $constraints = [];
        $columns = $constraintsConfig['columns'] ?? [];

        foreach ($columns as $column => $columnConstraints) {
            // Ensure columnConstraints is an array
            if (!is_array($columnConstraints)) {
                continue;
            }
            
            foreach ($columnConstraints as $constraint) {
                // Validate constraint structure
                if (!is_array($constraint) || !isset($constraint['type'])) {
                    continue;
                }
                
                $constraintSql = $this->buildConstraintSql($column, $constraint);
                if ($constraintSql) {
                    $constraints[] = [
                        'column' => $column,
                        'type' => $constraint['type'],
                        'sql' => $constraintSql,
                        'name' => $this->generateConstraintName($tableName, $column, $constraint['type'])
                    ];
                }
            }
        }

        return $constraints;
    }

    /**
     * Build SQL for a constraint.
     *
     * @param string $column The column name
     * @param array $constraint The constraint configuration
     * @return string|null The constraint SQL
     */
    protected function buildConstraintSql(string $column, array $constraint): ?string
    {
        // Validate constraint structure
        if (!isset($constraint['type'])) {
            return null;
        }
        
        $constraintMappings = $this->getConfigValue('database_constraints', []);
        
        // Fallback mappings if config is not available
        if (empty($constraintMappings)) {
            $constraintMappings = [
                'min' => 'CHECK ({column} >= {value})',
                'max' => 'CHECK ({column} <= {value})',
                'between' => 'CHECK ({column} BETWEEN {min} AND {max})',
                'in' => 'CHECK ({column} IN ({values}))',
                'not_in' => 'CHECK ({column} NOT IN ({values}))',
                'regex' => 'CHECK ({column} REGEXP "{pattern}")',
                'length' => 'CHECK (LENGTH({column}) >= {value})',
            ];
        }
        
        $type = $constraint['type'];

        if (!isset($constraintMappings[$type])) {
            return null;
        }

        $template = $constraintMappings[$type];
        $replacements = ['column' => $column];

        switch ($type) {
            case 'min':
            case 'max':
            case 'length':
            case 'digits':
                if (!isset($constraint['value'])) {
                    return null;
                }
                $replacements['value'] = $constraint['value'];
                break;

            case 'between':
                if (!isset($constraint['min']) || !isset($constraint['max'])) {
                    return null;
                }
                $replacements['min'] = $constraint['min'];
                $replacements['max'] = $constraint['max'];
                break;

            case 'in':
            case 'not_in':
                if (!isset($constraint['values']) || !is_array($constraint['values'])) {
                    return null;
                }
                $values = array_map(function ($value) {
                    return "'" . addslashes($value) . "'";
                }, $constraint['values']);
                $replacements['values'] = implode(', ', $values);
                break;

            case 'regex':
                if (!isset($constraint['pattern'])) {
                    return null;
                }
                // For regex patterns, we need to handle escaping carefully
                $pattern = $constraint['pattern'];
                // Since we're using double quotes in the template, we need to escape double quotes in the pattern
                $pattern = str_replace('"', '\\"', $pattern);
                $replacements['pattern'] = $pattern;
                break;
        }

        $sql = str_replace(
            array_map(fn($key) => '{' . $key . '}', array_keys($replacements)),
            array_values($replacements),
            $template
        );

        // Handle special case for length constraints that might have operator placeholders
        if ($type === 'length' && strpos($sql, '{operator}') !== false) {
            $operator = $constraint['operator'] ?? '>=';
            $sql = str_replace('{operator}', $operator, $sql);
        }

        return $sql;
    }

    /**
     * Generate a constraint name.
     *
     * @param string $tableName The table name
     * @param string $column The column name
     * @param string $type The constraint type
     * @return string The constraint name
     */
    protected function generateConstraintName(string $tableName, string $column, string $type): string
    {
        return "chk_{$tableName}_{$column}_{$type}";
    }

    /**
     * Generate the constraints migration content.
     *
     * @param string $tableName The table name
     * @param array $constraints The constraints
     * @return string The migration content
     */
    protected function generateConstraintsMigration(string $tableName, array $constraints): string
    {
        $className = 'AddConstraintsTo' . Str::studly($tableName) . 'Table';
        $upStatements = [];
        $downStatements = [];

        // Add a check to ensure the table exists before adding constraints
        $upStatements[] = "        if (!Schema::hasTable('{$tableName}')) {";
        $upStatements[] = "            throw new \\Exception('Table {$tableName} does not exist. Please run the table creation migration first.');";
        $upStatements[] = "        }";
        $upStatements[] = "";

        $upStatements[] = "        if (\\DB::getDriverName() === 'sqlite') {";
        $upStatements[] = "            return;";
        $downStatements[] = "        if (\\DB::getDriverName() === 'sqlite') { return; }";

        $upStatements[] = "        } else {";
        $upStatements[] = "            // Other databases: Using named constraints";
        
        // Generate named constraints for other databases
        foreach ($constraints as $constraint) {
            $escapedSql = str_replace('"', '\\"', $constraint['sql']);
            $upStatements[] = "            DB::statement(\"ALTER TABLE {$tableName} ADD CONSTRAINT {$constraint['name']} {$escapedSql}\");";
            $downStatements[] = "            DB::statement(\"ALTER TABLE {$tableName} DROP CONSTRAINT {$constraint['name']}\");";
        }
        
        $upStatements[] = "        }";

        $upBody = implode("\n", $upStatements);
        $downBody = implode("\n", $downStatements);

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upBody}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downBody}
    }
};
PHP;
    }

    /**
     * Find Form Request files for a model.
     *
     * @param string $modelName The model name
     * @return array The found request files
     */
    protected function findFormRequestFiles(string $modelName): array
    {
        $requestFiles = [];
        $requestsDir = 'app/Http/Requests';

        if (!$this->filesystem->exists($requestsDir)) {
            return $requestFiles;
        }

        $patterns = [
            "{$modelName}Request.php",
            "Store{$modelName}Request.php",
            "Update{$modelName}Request.php",
            "Create{$modelName}Request.php",
        ];

        foreach ($patterns as $pattern) {
            $filePath = $requestsDir . '/' . $pattern;
            if ($this->filesystem->exists($filePath)) {
                $requestFiles[] = $filePath;
            }
        }

        return $requestFiles;
    }

    /**
     * Add validation rules to a Form Request file.
     *
     * @param string $requestFile The request file path
     * @param array $constraintsConfig The constraints configuration
     * @return string|null The updated content or null if no changes
     */
    protected function addValidationRulesToRequest(string $requestFile, array $constraintsConfig): ?string
    {
        $content = $this->filesystem->get($requestFile);
        $columns = $constraintsConfig['columns'] ?? [];

        if (empty($columns)) {
            return null;
        }

        $validationRules = $this->buildValidationRules($columns);

        if (empty($validationRules)) {
            return null;
        }

        $updatedContent = $this->insertValidationRules($content, $validationRules);

        return $updatedContent !== $content ? $updatedContent : null;
    }

    /**
     * Build validation rules from constraints.
     *
     * @param array $columns The column constraints
     * @return array The validation rules
     */
    protected function buildValidationRules(array $columns): array
    {
        $validationRules = [];
        $ruleMappings = $this->getConfigValue('validation_rules', []);
        
        // Fallback mappings if config is not available
        if (empty($ruleMappings)) {
            $ruleMappings = [
                'min' => 'min:{value}',
                'max' => 'max:{value}',
                'between' => 'between:{min},{max}',
                'in' => 'in:{values}',
                'not_in' => 'not_in:{values}',
                'regex' => 'regex:{pattern}',
                'length' => 'size:{value}',
                'digits' => 'digits:{value}',
                'alpha' => 'alpha',
                'alpha_num' => 'alpha_num',
                'email' => 'email',
                'url' => 'url',
                'ip' => 'ip',
                'json' => 'json',
                'uuid' => 'uuid',
                'date' => 'date',
                'before' => 'before:{date}',
                'after' => 'after:{date}',
                'confirmed' => 'confirmed',
                'same' => 'same:{field}',
                'different' => 'different:{field}',
            ];
        }

        foreach ($columns as $column => $columnConstraints) {
            $rules = [];

            foreach ($columnConstraints as $constraint) {
                $rule = $this->buildValidationRule($constraint, $ruleMappings);
                if ($rule) {
                    $rules[] = $rule;
                }
            }

            if (!empty($rules)) {
                $validationRules[$column] = $rules;
            }
        }

        return $validationRules;
    }

    /**
     * Build a validation rule from a constraint.
     *
     * @param array $constraint The constraint configuration
     * @param array $ruleMappings The rule mappings
     * @return string|null The validation rule
     */
    protected function buildValidationRule(array $constraint, array $ruleMappings): ?string
    {
        $type = $constraint['type'];

        if (!isset($ruleMappings[$type])) {
            return null;
        }

        $template = $ruleMappings[$type];
        $replacements = [];

        switch ($type) {
            case 'min':
            case 'max':
            case 'length':
            case 'digits':
                $replacements['value'] = $constraint['value'];
                break;

            case 'between':
                $replacements['min'] = $constraint['min'];
                $replacements['max'] = $constraint['max'];
                break;

            case 'in':
            case 'not_in':
                $replacements['values'] = implode(',', $constraint['values']);
                break;

            case 'regex':
                $replacements['pattern'] = '/' . addslashes($constraint['pattern']) . '/';
                break;

            case 'before':
            case 'after':
                $replacements['date'] = $constraint['date'];
                break;

            case 'same':
            case 'different':
                $replacements['field'] = $constraint['field'];
                break;
        }

        return str_replace(
            array_map(fn($key) => '{' . $key . '}', array_keys($replacements)),
            array_values($replacements),
            $template
        );
    }

    /**
     * Insert validation rules into Form Request content.
     *
     * @param string $content The original content
     * @param array $validationRules The validation rules
     * @return string The updated content
     */
    protected function insertValidationRules(string $content, array $validationRules): string
    {
        // Try to find existing rules method and update it
        if (preg_match('/(public function rules\(\):\s*array\s*\{[^}]*return\s*\[)([^}]*)(\];[^}]*\})/s', $content, $matches)) {
            $beforeRules = $matches[1];
            $existingRules = $matches[2];
            $afterRules = $matches[3];
            
            // Parse existing rules to avoid duplicates
            $existingRulesArray = [];
            if (preg_match_all("/'([^']+)'\s*=>\s*\[([^\]]+)\]/", $existingRules, $ruleMatches, PREG_SET_ORDER)) {
                foreach ($ruleMatches as $ruleMatch) {
                    $column = $ruleMatch[1];
                    $rules = $ruleMatch[2];
                    $existingRulesArray[$column] = $rules;
                }
            }
            
            // Merge with new validation rules
            foreach ($validationRules as $column => $rules) {
                $rulesString = "'" . implode("', '", $rules) . "'";
                if (isset($existingRulesArray[$column])) {
                    // Merge with existing rules
                    $existingRulesArray[$column] = $existingRulesArray[$column] . ", '" . implode("', '", $rules) . "'";
                } else {
                    // Add new rules
                    $existingRulesArray[$column] = $rulesString;
                }
            }
            
            // Rebuild the rules array
            $newRulesArray = "";
            foreach ($existingRulesArray as $column => $rules) {
                $newRulesArray .= "            '{$column}' => [{$rules}],\n";
            }
            
            return $beforeRules . $newRulesArray . $afterRules;
        }
        
        // If no rules method exists, create one
        $rulesCode = "";
        foreach ($validationRules as $column => $rules) {
            $rulesString = "'" . implode("', '", $rules) . "'";
            $rulesCode .= "            '{$column}' => [{$rulesString}],\n";
        }
        
        $rulesMethod = "\n    /**\n     * Get the validation rules that apply to the request.\n     */\n    public function rules(): array\n    {\n        return [\n{$rulesCode}        ];\n    }\n";
        
        return preg_replace('/(\}\s*)$/', $rulesMethod . '$1', $content);
    }

    /**
     * Create a validation rules file.
     *
     * @param string $modelName The model name
     * @param array $constraintsConfig The constraints configuration
     * @return array|null The created file info
     */
    protected function createValidationRulesFile(string $modelName, array $constraintsConfig): ?array
    {
        $className = $modelName . 'ConstraintRules';
        $filePath = 'app/Rules/' . $className . '.php';
        $columns = $constraintsConfig['columns'] ?? [];

        if (empty($columns)) {
            return null;
        }

        $validationRules = $this->buildValidationRules($columns);

        if (empty($validationRules)) {
            return null;
        }

        $content = $this->generateValidationRulesClass($className, $validationRules);
        $this->filesystem->put($filePath, $content);

        return ['path' => $filePath, 'content' => $content];
    }

    /**
     * Generate a validation rules class.
     *
     * @param string $className The class name
     * @param array $validationRules The validation rules
     * @return string The class content
     */
    protected function generateValidationRulesClass(string $className, array $validationRules): string
    {
        $rulesCode = "";
        foreach ($validationRules as $column => $rules) {
            $rulesString = "'" . implode('|', $rules) . "'";
            $rulesCode .= "            '{$column}' => {$rulesString},\n";
        }

        return <<<PHP
<?php

namespace App\Rules;

class {$className}
{
    /**
     * Get the validation rules for constraints.
     */
    public static function rules(): array
    {
        return [
{$rulesCode}        ];
    }
}
PHP;
    }

    /**
     * Add mutators to a model.
     *
     * @param string $modelContent The model content
     * @param string $modelName The model name
     * @param array $constraintsConfig The constraints configuration
     * @return string The updated model content
     */
    protected function addMutatorsToModel(string $modelContent, string $modelName, array $constraintsConfig): string
    {
        // This is a placeholder implementation
        // In a real implementation, you would add model mutators for runtime validation
        return $modelContent;
    }

    protected function getSchemaService(): DatabaseSchemaService
    {
        if ($this->schemaService === null) {
            $this->schemaService = App::make(DatabaseSchemaService::class);
        }
        return $this->schemaService;
    }
} 
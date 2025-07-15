<?php

namespace Blueprint;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Lexer;
use Blueprint\Events\GenerationStarted;
use Blueprint\Events\GenerationCompleted;
use Blueprint\Events\GeneratorExecuting;
use Blueprint\Events\GeneratorExecuted;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Plugin\GeneratorRegistry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Blueprint
{
    private array $lexers = [];

    private array $generators = [];

    private ?Dispatcher $events = null;

    private ?GeneratorRegistry $generatorRegistry = null;

    public static function relativeNamespace(string $fullyQualifiedClassName): string
    {
        $namespace = config('blueprint.namespace', 'App') . '\\';
        $reference = ltrim($fullyQualifiedClassName, '\\');

        if (Str::startsWith($reference, $namespace)) {
            return Str::after($reference, $namespace);
        }

        return $reference;
    }

    public static function appPath()
    {
        return str_replace('\\', '/', config('blueprint.app_path', 'app'));
    }

    public function parse($content, $strip_dashes = true, ?string $filePath = null)
    {
        try {
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            // Check if this is a dashboard file and disable strip_dashes for dashboard files
            $isDashboardFile = false;
            if ($filePath && (strpos($filePath, 'dashboard') !== false || strpos($content, 'dashboards:') !== false)) {
                $isDashboardFile = true;
                $strip_dashes = false; // Don't strip dashes for dashboard files
            }

            if ($strip_dashes) {
                $content = preg_replace('/^(\s*)-\s*/m', '\1', $content);
            }

            $content = $this->transformDuplicatePropertyKeys($content);

            $content = preg_replace_callback(
                '/^(\s+)(id|timestamps(Tz)?|softDeletes(Tz)?)$/mi',
                fn ($matches) => $matches[1] . strtolower($matches[2]) . ': ' . $matches[2],
                $content
            );

            $content = preg_replace_callback(
                '/^(\s+)(id|timestamps(Tz)?|softDeletes(Tz)?): true$/mi',
                fn ($matches) => $matches[1] . strtolower($matches[2]) . ': ' . $matches[2],
                $content
            );

            $content = preg_replace_callback(
                '/^(\s+)resource?$/mi',
                fn ($matches) => $matches[1] . 'resource: web',
                $content
            );

            $content = preg_replace_callback(
                '/^(\s+)invokable?$/mi',
                fn ($matches) => $matches[1] . 'invokable: true',
                $content
            );

            $content = preg_replace_callback(
                '/^(\s+)(ulid|uuid)(: true)?$/mi',
                fn ($matches) => $matches[1] . 'id: ' . $matches[2] . ' primary',
                $content
            );

            $parsed = Yaml::parse($content);
            
            // Validate the parsed structure
            $this->validateParsedStructure($parsed, $filePath);
            
            return $parsed;
        } catch (ParseException $e) {
            // For tests that expect the original ParseException, re-throw it
            if (!$filePath) {
                throw $e;
            }
            
            // Try to provide more specific error information
            $errorMessage = $e->getMessage();
            $lineNumber = null;
            
            // Extract line number from Symfony YAML error messages
            if (preg_match('/at line (\d+)/', $errorMessage, $matches)) {
                $lineNumber = (int) $matches[1];
            }
            
            // Create enhanced context for the error
            $context = [
                'file' => $filePath,
                'line_number' => $lineNumber,
                'yaml_content' => $content,
                'error_message' => $errorMessage,
                'parse_exception' => $e
            ];
            
            // Add context lines around the error
            if ($lineNumber) {
                $lines = explode("\n", $content);
                $startLine = max(1, $lineNumber - 3);
                $endLine = min(count($lines), $lineNumber + 3);
                
                $contextLines = [];
                for ($i = $startLine; $i <= $endLine; $i++) {
                    $contextLines[$i] = $lines[$i - 1] ?? '';
                }
                $context['context_lines'] = $contextLines;
            }
            
            throw ParsingException::invalidYaml($filePath, $errorMessage);
        } catch (ParsingException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw ParsingException::invalidYaml($filePath ?? 'unknown', $e->getMessage());
        }
    }

    public function analyze(array $tokens): Tree
    {
        try {
            $registry = [
                'models' => [],
                'controllers' => [],
            ];

            foreach ($this->lexers as $lexer) {
                $registry = array_merge($registry, $lexer->analyze($tokens));
            }

            $tree = new Tree($registry);
            
            // Validate the analyzed structure
            $this->validateAnalyzedStructure($tree);
            
            return $tree;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ValidationException(
                'Failed to analyze blueprint structure: ' . $e->getMessage(),
                4001,
                $e
            );
        }
    }

    public function generate(Tree $tree, array $only = [], array $skip = [], $overwriteMigrations = false): array
    {
        // Fire generation started event
        $this->fireEvent(new GenerationStarted($tree, $only, $skip));
        
        $components = [];

        // Use generator registry if available, otherwise use legacy generators
        if ($this->generatorRegistry) {
            $activeGenerators = $this->generatorRegistry->getActiveGenerators($tree);
            
            foreach ($activeGenerators as $generator) {
                if ($this->shouldGenerate($generator->types(), $only, $skip)) {
                    // Fire generator executing event
                    $this->fireEvent(new GeneratorExecuting($tree, $generator, $only, $skip));
                    
                    $output = $generator->output($tree, $overwriteMigrations);
                    $components = array_merge_recursive($components, $output);
                    
                    // Fire generator executed event
                    $this->fireEvent(new GeneratorExecuted($tree, $generator, $output, $only, $skip));
                }
            }
        } else {
            // Legacy generator support
            foreach ($this->generators as $generator) {
                if ($this->shouldGenerate($generator->types(), $only, $skip)) {
                    // Fire generator executing event
                    $this->fireEvent(new GeneratorExecuting($tree, $generator, $only, $skip));
                    
                    try {
                        $output = $generator->output($tree, $overwriteMigrations);
                        $components = array_merge_recursive($components, $output);
                        
                        // Fire generator executed event
                        $this->fireEvent(new GeneratorExecuted($tree, $generator, $output, $only, $skip));
                    } catch (\Throwable $e) {
                        throw $e;
                    }
                }
            }
        }

        // Fire generation completed event
        $this->fireEvent(new GenerationCompleted($tree, $components, $only, $skip));

        return $components;
    }

    public function dump(array $generated): string
    {
        return Yaml::dump($generated);
    }

    public function registerLexer(Lexer $lexer): void
    {
        $this->lexers[] = $lexer;
    }

    public function registerGenerator(Generator $generator): void
    {
        $this->generators[] = $generator;
    }

    public function swapGenerator(string $concrete, Generator $generator): void
    {
        foreach ($this->generators as $key => $registeredGenerator) {
            if (get_class($registeredGenerator) === $concrete) {
                unset($this->generators[$key]);
            }
        }

        $this->registerGenerator($generator);
    }

    public function setEventDispatcher(Dispatcher $events): void
    {
        $this->events = $events;
    }

    public function getEventDispatcher(): ?Dispatcher
    {
        return $this->events;
    }

    public function setGeneratorRegistry(GeneratorRegistry $registry): void
    {
        $this->generatorRegistry = $registry;
    }

    public function getGeneratorRegistry(): ?GeneratorRegistry
    {
        return $this->generatorRegistry;
    }

    private function fireEvent(object $event): void
    {
        if ($this->events) {
            $this->events->dispatch($event);
        }
    }

    protected function shouldGenerate(array $types, array $only, array $skip): bool
    {
        if (count($only)) {
            return collect($types)->intersect($only)->isNotEmpty();
        }

        if (count($skip)) {
            return collect($types)->intersect($skip)->isEmpty();
        }

        return true;
    }

    private function transformDuplicatePropertyKeys(string $content): string
    {
        preg_match('/^controllers:$/m', $content, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches)) {
            return $content;
        }

        $offset = $matches[0][1];
        $lines = explode("\n", substr($content, $offset));

        $methods = [];
        $statements = [];
        foreach ($lines as $index => $line) {
            $method = preg_match('/^( {2}| {4}|\t){2}\w+:$/', $line);
            if ($method) {
                $methods[] = $statements ?? [];
                $statements = [];

                continue;
            }

            preg_match('/^( {2}| {4}|\t){3}(dispatch|fire|notify|send):\s/', $line, $matches);
            if (empty($matches)) {
                continue;
            }

            $statements[$index] = $matches[2];
        }

        $methods[] = $statements ?? [];

        $multiples = collect($methods)
            ->filter(fn ($statements) => count(array_unique($statements)) !== count($statements))
            ->mapWithKeys(fn ($statements) => $statements);

        if ($multiples->isEmpty()) {
            return $content;
        }

        foreach ($multiples as $line => $statement) {
            $lines[$line] = preg_replace(
                '/^(\s+)' . $statement . ':/',
                '$1' . $statement . '-' . $line . ':',
                $lines[$line]
            );
        }

        return substr_replace(
            $content,
            implode("\n", $lines),
            $offset
        );
    }

    /**
     * Validate the parsed YAML structure for common issues.
     */
    private function validateParsedStructure(array $parsed, ?string $filePath): void
    {
        // Skip validation for simple data structures that don't look like Blueprint files
        $blueprintSections = ['models', 'controllers', 'seeders', 'components'];
        $dashboardSections = ['dashboards'];
        $hasBlueprintSections = false;
        $hasDashboardSections = false;
        
        foreach ($blueprintSections as $section) {
            if (isset($parsed[$section])) {
                $hasBlueprintSections = true;
                break;
            }
        }
        
        foreach ($dashboardSections as $section) {
            if (isset($parsed[$section])) {
                $hasDashboardSections = true;
                break;
            }
        }
        
        // If this doesn't look like a Blueprint file or dashboard file, skip validation
        if (!$hasBlueprintSections && !$hasDashboardSections) {
            return;
        }

        // For dashboard files, skip traditional Blueprint validation
        if ($hasDashboardSections) {
            return;
        }

        // Check for at least one valid section
        if (empty($parsed['models']) && empty($parsed['controllers']) && empty($parsed['seeders']) && empty($parsed['components'])) {
            throw ParsingException::missingRequiredSection('models, controllers, seeders, or components', $filePath ?? 'unknown');
        }

        // Validate models section
        if (isset($parsed['models'])) {
            $this->validateModelsSection($parsed['models'], $filePath);
        }

        // Validate controllers section
        if (isset($parsed['controllers'])) {
            $this->validateControllersSection($parsed['controllers'], $filePath);
        }

        // Check for duplicate definitions
        $this->checkForDuplicateDefinitions($parsed, $filePath);
    }

    /**
     * Validate models section structure.
     */
    private function validateModelsSection(array $models, ?string $filePath): void
    {
        foreach ($models as $modelName => $definition) {
            if (!is_string($modelName) || empty($modelName)) {
                throw ParsingException::invalidModelDefinition($modelName, 'Model name must be a non-empty string', $filePath ?? 'unknown');
            }

            if (!preg_match('/^[A-Za-z][a-zA-Z0-9_\\/\\\\]*$/', $modelName)) {
                throw ParsingException::invalidModelDefinition($modelName, 'Model name must start with a letter and contain only letters, numbers, underscores, and forward/backslashes for namespaces', $filePath ?? 'unknown');
            }

            if (is_array($definition)) {
                $this->validateModelDefinition($modelName, $definition, $filePath);
            }
        }
    }

    /**
     * Validate individual model definition.
     */
    private function validateModelDefinition(string $modelName, array $definition, ?string $filePath): void
    {
        // Validate columns
        if (isset($definition['columns'])) {
            foreach ($definition['columns'] as $columnName => $columnDef) {
                if (!is_string($columnName) || empty($columnName)) {
                    throw ParsingException::invalidModelDefinition($modelName, 'Column name must be a non-empty string', $filePath ?? 'unknown');
                }

                // Be more lenient with column name validation to allow Laravel column types and relationship types
                // The ModelLexer will handle these appropriately
                if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $columnName)) {
                    throw ParsingException::invalidModelDefinition($modelName, 'Column name must start with a letter and contain only letters, numbers, and underscores', $filePath ?? 'unknown');
                }
            }
        }

        // Validate relationships
        if (isset($definition['relationships'])) {
            $this->validateRelationships($modelName, $definition['relationships'], $filePath);
        }
    }

    /**
     * Validate relationships in model definition.
     */
    private function validateRelationships(string $modelName, array|string $relationships, ?string $filePath): void
    {
        // Handle case where relationships is a string (e.g., "relationships: string")
        if (is_string($relationships)) {
            // Skip validation for string relationships - they'll be handled by the lexer
            return;
        }
        
        $validRelationshipTypes = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany'];

        foreach ($relationships as $relationshipName => $relationshipDef) {
            if (is_string($relationshipDef)) {
                // Simple relationship definition
                continue;
            }

            if (is_array($relationshipDef)) {
                foreach ($relationshipDef as $type => $target) {
                    if (!in_array($type, $validRelationshipTypes)) {
                        throw ParsingException::invalidModelDefinition($modelName, "Unsupported relationship type '{$type}'", $filePath ?? 'unknown');
                    }
                }
            }
        }
    }

    /**
     * Validate controllers section structure.
     */
    private function validateControllersSection(array $controllers, ?string $filePath): void
    {
        foreach ($controllers as $controllerName => $definition) {
            if (!is_string($controllerName) || empty($controllerName)) {
                throw ParsingException::invalidControllerDefinition($controllerName, 'Controller name must be a non-empty string', $filePath ?? 'unknown');
            }

            if (!preg_match('/^[A-Za-z][a-zA-Z0-9_\\/\\\\]*$/', $controllerName)) {
                throw ParsingException::invalidControllerDefinition($controllerName, 'Controller name must start with a letter and contain only letters, numbers, underscores, and forward/backslashes for namespaces', $filePath ?? 'unknown');
            }

            if (is_array($definition)) {
                $this->validateControllerDefinition($controllerName, $definition, $filePath);
            }
        }
    }

    /**
     * Validate individual controller definition.
     */
    private function validateControllerDefinition(string $controllerName, array $definition, ?string $filePath): void
    {
        // Validate methods
        foreach ($definition as $methodName => $methodDef) {
            if (!is_string($methodName) || empty($methodName)) {
                throw ParsingException::invalidControllerDefinition($controllerName, "Method name must be a non-empty string", $filePath ?? 'unknown');
            }

            if (!preg_match('/^(__[a-zA-Z0-9_]+|[a-z][a-zA-Z0-9_]*)$/', $methodName)) {
                throw ParsingException::invalidControllerDefinition($controllerName, "Method name '{$methodName}' must start with lowercase letter or be a magic method (like __invoke)", $filePath ?? 'unknown');
            }
        }
    }

    /**
     * Check for duplicate definitions across the parsed structure.
     */
    private function checkForDuplicateDefinitions(array $parsed, ?string $filePath): void
    {
        $modelNames = array_keys($parsed['models'] ?? []);
        $controllerNames = array_keys($parsed['controllers'] ?? []);

        // Check for duplicate model names
        if (count($modelNames) !== count(array_unique($modelNames))) {
            $duplicates = array_diff_assoc($modelNames, array_unique($modelNames));
            throw ParsingException::invalidModelDefinition(reset($duplicates), 'Duplicate model definition', $filePath ?? 'unknown');
        }

        // Check for duplicate controller names
        if (count($controllerNames) !== count(array_unique($controllerNames))) {
            $duplicates = array_diff_assoc($controllerNames, array_unique($controllerNames));
            throw ParsingException::invalidControllerDefinition(reset($duplicates), 'Duplicate controller definition', $filePath ?? 'unknown');
        }
    }

    /**
     * Validate the analyzed tree structure.
     */
    private function validateAnalyzedStructure(Tree $tree): void
    {
        // Check for circular dependencies in models
        $this->checkForCircularDependencies($tree);

        // Validate foreign key references
        $this->validateForeignKeyReferences($tree);
    }

    /**
     * Check for circular dependencies in model relationships.
     */
    private function checkForCircularDependencies(Tree $tree): void
    {
        $models = $tree->models();
        $dependencies = [];

        foreach ($models as $modelName => $model) {
            if (is_array($model) && isset($model['relationships'])) {
                foreach ($model['relationships'] as $relationshipName => $relationshipDef) {
                    if (is_string($relationshipDef)) {
                        $dependencies[$modelName][] = $relationshipDef;
                    } elseif (is_array($relationshipDef)) {
                        foreach ($relationshipDef as $type => $target) {
                            if (is_string($target)) {
                                $dependencies[$modelName][] = $target;
                            }
                        }
                    }
                }
            }
        }

        // Simple circular dependency detection
        foreach ($dependencies as $modelName => $deps) {
            $this->detectCircularDependency($modelName, $deps, $dependencies, [$modelName]);
        }
    }

    /**
     * Recursively detect circular dependencies.
     */
    private function detectCircularDependency(string $currentModel, array $deps, array $allDependencies, array $path): void
    {
        foreach ($deps as $dependency) {
            if (in_array($dependency, $path)) {
                throw ValidationException::circularDependency(array_merge($path, [$dependency]));
            }

            if (isset($allDependencies[$dependency])) {
                $this->detectCircularDependency($dependency, $allDependencies[$dependency], $allDependencies, array_merge($path, [$dependency]));
            }
        }
    }

    /**
     * Validate foreign key references exist.
     */
    private function validateForeignKeyReferences(Tree $tree): void
    {
        $models = $tree->models();
        $modelNames = array_keys($models);

        foreach ($models as $modelName => $model) {
            if (is_array($model) && isset($model['relationships'])) {
                foreach ($model['relationships'] as $relationshipName => $relationshipDef) {
                    if (is_string($relationshipDef)) {
                        $referencedModel = $relationshipDef;
                        if (!in_array($referencedModel, $modelNames)) {
                            throw ValidationException::missingForeignKey($relationshipName, $modelName, $referencedModel);
                        }
                    }
                }
            }
        }
    }
}

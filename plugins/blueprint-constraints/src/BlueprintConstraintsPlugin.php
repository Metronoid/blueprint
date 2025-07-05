<?php

namespace BlueprintExtensions\Constraints;

use Blueprint\Plugin\AbstractPlugin;
use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Lexer;
use Blueprint\Contracts\PluginManager;
use Blueprint\Plugin\GeneratorRegistry;
use BlueprintExtensions\Constraints\Lexers\ConstraintsLexer;
use BlueprintExtensions\Constraints\Generators\ConstraintsGenerator;
use Illuminate\Container\Container;

class BlueprintConstraintsPlugin extends AbstractPlugin
{
    protected string $name = 'blueprint-constraints';
    protected string $version = '1.0.0';
    protected string $description = 'A powerful extension for Laravel Blueprint that adds support for column constraints and validation rules';
    protected string $author = 'Blueprint Extensions';
    protected array $dependencies = [
        'laravel-shift/blueprint' => '^2.0'
    ];

    protected array $configSchema = [
        'generate_database_constraints' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate database constraints'
        ],
        'generate_validation_rules' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate validation rules'
        ],
        'generate_model_mutators' => [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Whether to generate model mutators'
        ]
    ];

    public function register(): void
    {
        try {
            $app = Container::getInstance();
            
            // Load default configuration
            $configPath = __DIR__ . '/../config/blueprint-constraints.php';
            $defaultConfig = [];
            
            if (file_exists($configPath)) {
                $defaultConfig = require $configPath;
            }
            
            // Merge with plugin-specific configuration
            $config = $app->make('config');
            $config->set(
                'blueprint-constraints',
                array_merge($defaultConfig, $this->getConfig())
            );
        } catch (\Exception $e) {
            // Silently fail during registration to avoid breaking the application
        }
    }

    public function boot(): void
    {
        try {
            $app = Container::getInstance();
            
            // Register the lexer with Blueprint directly
            if ($app->bound(\Blueprint\Blueprint::class)) {
                $blueprint = $app->make(\Blueprint\Blueprint::class);
                $lexer = new ConstraintsLexer();
                $blueprint->registerLexer($lexer);
            }
            
            // Register the generator with the plugin system
            if ($app->bound(GeneratorRegistry::class)) {
                $generatorRegistry = $app->make(GeneratorRegistry::class);
                $generator = new ConstraintsGenerator($app->make('files'), $this);
                $generatorRegistry->registerPluginGenerator($generator);
            }
        } catch (\Exception $e) {
            // Silently fail during boot to avoid breaking the application
        }
    }

    public function isCompatible(string $blueprintVersion): bool
    {
        return version_compare($blueprintVersion, '2.0.0', '>=');
    }
} 
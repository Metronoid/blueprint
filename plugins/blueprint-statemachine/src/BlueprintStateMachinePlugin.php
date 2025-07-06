<?php

namespace BlueprintExtensions\StateMachine;

use Blueprint\Plugin\AbstractPlugin;
use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Lexer;
use Blueprint\Contracts\PluginManager;
use Blueprint\Plugin\GeneratorRegistry;
use BlueprintExtensions\StateMachine\Lexers\StateMachineLexer;
use BlueprintExtensions\StateMachine\Generators\StateMachineGenerator;
use Illuminate\Container\Container;

class BlueprintStateMachinePlugin extends AbstractPlugin
{
    protected string $name = 'blueprint-statemachine';
    protected string $version = '1.0.0';
    protected string $description = 'A powerful Blueprint extension that adds state machine functionality to Laravel models';
    protected string $author = 'Blueprint Extensions';
    protected array $dependencies = [
        'laravel-shift/blueprint' => '^2.0'
    ];

    protected array $configSchema = [
        'generate_trait' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate state machine traits'
        ],
        'generate_events' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate state machine events'
        ],
        'generate_observers' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate state machine observers'
        ],
        'generate_middleware' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate state machine middleware'
        ],
        'generate_tests' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate state machine tests'
        ],
        'track_state_history' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to track state transitions'
        ]
    ];

    public function register(): void
    {
        try {
            $app = Container::getInstance();
            
            // Load default configuration
            $configPath = __DIR__ . '/../config/blueprint-statemachine.php';
            $defaultConfig = [];
            
            if (file_exists($configPath)) {
                $defaultConfig = require $configPath;
            }
            
            // Merge with plugin-specific configuration
            $config = $app->make('config');
            $config->set(
                'blueprint-statemachine',
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
                $lexer = new StateMachineLexer();
                $blueprint->registerLexer($lexer);
            }
            
            // Register the generator with the plugin system
            if ($app->bound(GeneratorRegistry::class)) {
                $generatorRegistry = $app->make(GeneratorRegistry::class);
                $generator = new StateMachineGenerator($app->make('files'), $this);
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
<?php

namespace BlueprintExtensions\Auditing;

use Blueprint\Plugin\AbstractPlugin;
use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Lexer;
use Blueprint\Contracts\PluginManager;
use Blueprint\Plugin\GeneratorRegistry;
use BlueprintExtensions\Auditing\Lexers\AuditingLexer;
use BlueprintExtensions\Auditing\Generators\AuditingGenerator;
use Illuminate\Container\Container;

class BlueprintAuditingPlugin extends AbstractPlugin
{
    protected string $name = 'blueprint-auditing';
    protected string $version = '1.0.0';
    protected string $description = 'Laravel Blueprint extension for Laravel Auditing package integration';
    protected string $author = 'Blueprint Extensions';
    protected array $dependencies = [
        'laravel-shift/blueprint' => '^2.0'
    ];

    protected array $configSchema = [
        'generate_auditing' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate auditing configuration'
        ],
        'generate_rewind' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate rewind functionality'
        ],
        'generate_migrations' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate audits migration'
        ],
        'generate_custom_models' => [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Whether to generate custom audit models'
        ]
    ];

    public function register(): void
    {
        try {
            $app = Container::getInstance();
            
            // Load default configuration
            $configPath = __DIR__ . '/../config/blueprint-auditing.php';
            $defaultConfig = [];
            
            if (file_exists($configPath)) {
                $defaultConfig = require $configPath;
            }
            
            // Merge with plugin-specific configuration
            $config = $app->make('config');
            $config->set(
                'blueprint-auditing',
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
                $lexer = new AuditingLexer();
                $blueprint->registerLexer($lexer);
            }
            
            // Register the generator with the plugin system
            if ($app->bound(GeneratorRegistry::class)) {
                $generatorRegistry = $app->make(GeneratorRegistry::class);
                $generator = new AuditingGenerator($app->make('files'), $this);
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
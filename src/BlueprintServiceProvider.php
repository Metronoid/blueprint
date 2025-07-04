<?php

namespace Blueprint;

use Blueprint\Commands\BuildCommand;
use Blueprint\Commands\EraseCommand;
use Blueprint\Commands\InitCommand;
use Blueprint\Commands\NewCommand;
use Blueprint\Commands\PublishStubsCommand;
use Blueprint\Commands\TraceCommand;
use Blueprint\Contracts\PluginDiscovery;
use Blueprint\Contracts\PluginManager;
use Blueprint\Plugin\PluginDiscovery as ConcretePluginDiscovery;
use Blueprint\Plugin\PluginManager as ConcretePluginManager;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class BlueprintServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot(): void
    {
        if (!defined('STUBS_PATH')) {
            define('STUBS_PATH', dirname(__DIR__) . '/stubs');
        }

        if (!defined('CUSTOM_STUBS_PATH')) {
            define('CUSTOM_STUBS_PATH', base_path('stubs/blueprint'));
        }

        $this->publishes([
            __DIR__ . '/../config/blueprint.php' => config_path('blueprint.php'),
        ], 'blueprint-config');

        $this->publishes([
            dirname(__DIR__) . '/stubs' => CUSTOM_STUBS_PATH,
        ], 'blueprint-stubs');

        // Boot plugin system
        $this->bootPluginSystem();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/blueprint.php',
            'blueprint'
        );

        File::mixin(new FileMixins);

        // Register plugin system
        $this->registerPluginSystem();

        $this->app->bind('command.blueprint.build', fn ($app) => new BuildCommand($app['files'], app(Builder::class)));
        $this->app->bind('command.blueprint.erase', fn ($app) => new EraseCommand($app['files']));
        $this->app->bind('command.blueprint.trace', fn ($app) => new TraceCommand($app['files'], app(Tracer::class)));
        $this->app->bind('command.blueprint.new', fn ($app) => new NewCommand($app['files']));
        $this->app->bind('command.blueprint.init', fn ($app) => new InitCommand);
        $this->app->bind('command.blueprint.stubs', fn ($app) => new PublishStubsCommand);

        $this->app->singleton(Blueprint::class, function ($app) {
            $blueprint = new Blueprint;
            
            // Set up event dispatcher
            $blueprint->setEventDispatcher($app['events']);
            
            $blueprint->registerLexer(new \Blueprint\Lexers\ConfigLexer($app));
            $blueprint->registerLexer(new \Blueprint\Lexers\ModelLexer);
            $blueprint->registerLexer(new \Blueprint\Lexers\SeederLexer);
            $blueprint->registerLexer(new \Blueprint\Lexers\ControllerLexer(new \Blueprint\Lexers\StatementLexer));

            foreach (config('blueprint.generators') as $generator) {
                $blueprint->registerGenerator(new $generator($app['files']));
            }

            return $blueprint;
        });

        $this->app->make('events')->listen(CommandFinished::class, function ($event) {
            if ($event->command == 'stub:publish') {
                $this->app->make(Kernel::class)->queue('blueprint:stubs');
            }
        });

        $this->commands([
            'command.blueprint.build',
            'command.blueprint.erase',
            'command.blueprint.trace',
            'command.blueprint.new',
            'command.blueprint.init',
            'command.blueprint.stubs',
        ]);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'command.blueprint.build',
            'command.blueprint.erase',
            'command.blueprint.trace',
            'command.blueprint.new',
            'command.blueprint.init',
            Blueprint::class,
            PluginDiscovery::class,
            PluginManager::class,
        ];
    }

    /**
     * Register the plugin system.
     */
    private function registerPluginSystem(): void
    {
        $this->app->singleton(PluginDiscovery::class, function ($app) {
            return new ConcretePluginDiscovery($app['files']);
        });

        $this->app->singleton(PluginManager::class, function ($app) {
            return new ConcretePluginManager(
                $app[PluginDiscovery::class],
                $app['events']
            );
        });
    }

    /**
     * Boot the plugin system.
     */
    private function bootPluginSystem(): void
    {
        if (!config('blueprint.plugins.enabled', true)) {
            return;
        }

        $pluginManager = $this->app[PluginManager::class];
        
        // Discover and register plugins
        $pluginManager->discoverPlugins();
        
        // Register plugin services
        $pluginManager->registerPluginServices();
        
        // Boot plugins
        $pluginManager->bootPlugins();
    }
}

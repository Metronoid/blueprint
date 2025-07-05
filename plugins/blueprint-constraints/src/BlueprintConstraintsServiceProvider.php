<?php

namespace BlueprintExtensions\Constraints;

use Illuminate\Support\ServiceProvider;
use Blueprint\Contracts\PluginManager;

class BlueprintConstraintsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the plugin class as a singleton
        $this->app->singleton(BlueprintConstraintsPlugin::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->registerPlugin();
    }

    /**
     * Register the plugin with Blueprint's plugin manager.
     */
    protected function registerPlugin(): void
    {
        if ($this->app->bound(PluginManager::class)) {
            $pluginManager = $this->app->make(PluginManager::class);
            $plugin = $this->app->make(BlueprintConstraintsPlugin::class);
            $pluginManager->registerPlugin($plugin);
        }
    }

    /**
     * Publish the package configuration.
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__ . '/../config/blueprint-constraints.php' => config_path('blueprint-constraints.php'),
        ], 'blueprint-constraints-config');
    }
} 
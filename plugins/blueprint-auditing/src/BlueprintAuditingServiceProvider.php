<?php

namespace BlueprintExtensions\Auditing;

use Illuminate\Support\ServiceProvider;
use Blueprint\Contracts\PluginManager;

class BlueprintAuditingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the plugin class as a singleton
        $this->app->singleton(BlueprintAuditingPlugin::class);
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
            $plugin = $this->app->make(BlueprintAuditingPlugin::class);
            $pluginManager->registerPlugin($plugin);
        }
    }

    /**
     * Publish the package configuration.
     */
    protected function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__ . '/../config/blueprint-auditing.php' => config_path('blueprint-auditing.php'),
        ], 'blueprint-auditing-config');
    }
} 
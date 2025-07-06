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
        $this->publishViews();
        $this->loadRoutes();
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

    /**
     * Publish the package views.
     */
    protected function publishViews(): void
    {
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/blueprint-auditing'),
        ], 'blueprint-auditing-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'blueprint-auditing');
    }

    /**
     * Load the package routes.
     */
    protected function loadRoutes(): void
    {
        if (file_exists(__DIR__ . '/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
        if (file_exists(__DIR__ . '/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
} 
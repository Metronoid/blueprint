<?php

namespace Blueprint\Services;

use Blueprint\Contracts\DashboardPlugin;
use Blueprint\Models\Dashboard;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;

class DashboardPluginManager
{
    private Collection $plugins;
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->plugins = collect();
        $this->loadPlugins();
    }

    /**
     * Load all dashboard plugins
     */
    protected function loadPlugins(): void
    {
        $pluginPaths = [
            base_path('plugins'),
            dirname(__DIR__) . '/../plugins',
        ];

        foreach ($pluginPaths as $pluginPath) {
            if (!$this->filesystem->exists($pluginPath)) {
                continue;
            }

            $directories = $this->filesystem->directories($pluginPath);
            
            foreach ($directories as $directory) {
                $this->loadPluginFromDirectory($directory);
            }
        }
    }

    /**
     * Load a plugin from a directory
     */
    protected function loadPluginFromDirectory(string $directory): void
    {
        $manifestPath = $directory . '/blueprint.json';
        
        if (!$this->filesystem->exists($manifestPath)) {
            return;
        }

        $manifest = json_decode($this->filesystem->get($manifestPath), true);
        
        if (!isset($manifest['dashboard_plugin'])) {
            return;
        }

        $pluginClass = $manifest['dashboard_plugin'];
        
        if (class_exists($pluginClass) && is_subclass_of($pluginClass, DashboardPlugin::class)) {
            $plugin = app($pluginClass);
            $this->plugins->put($plugin->getName(), $plugin);
        }
    }

    /**
     * Get all loaded plugins
     */
    public function getPlugins(): Collection
    {
        return $this->plugins;
    }

    /**
     * Get enabled plugins
     */
    public function getEnabledPlugins(): Collection
    {
        return $this->plugins->filter(fn($plugin) => $plugin->isEnabled());
    }

    /**
     * Get a specific plugin by name
     */
    public function getPlugin(string $name): ?DashboardPlugin
    {
        return $this->plugins->get($name);
    }

    /**
     * Extend a dashboard with all enabled plugins
     */
    public function extendDashboard(Dashboard $dashboard): void
    {
        $this->getEnabledPlugins()->each(function ($plugin) use ($dashboard) {
            $plugin->extendDashboard($dashboard);
        });
    }

    /**
     * Get all widgets from enabled plugins
     */
    public function getPluginWidgets(): array
    {
        $widgets = [];
        
        $this->getEnabledPlugins()->each(function ($plugin) use (&$widgets) {
            $pluginWidgets = $plugin->getWidgets();
            $widgets = array_merge($widgets, $pluginWidgets);
        });
        
        return $widgets;
    }

    /**
     * Get all navigation items from enabled plugins
     */
    public function getPluginNavigation(): array
    {
        $navigation = [];
        
        $this->getEnabledPlugins()->each(function ($plugin) use (&$navigation) {
            $pluginNavigation = $plugin->getNavigation();
            $navigation = array_merge($navigation, $pluginNavigation);
        });
        
        return $navigation;
    }

    /**
     * Get all permissions from enabled plugins
     */
    public function getPluginPermissions(): array
    {
        $permissions = [];
        
        $this->getEnabledPlugins()->each(function ($plugin) use (&$permissions) {
            $pluginPermissions = $plugin->getPermissions();
            $permissions = array_merge($permissions, $pluginPermissions);
        });
        
        return $permissions;
    }

    /**
     * Get all API endpoints from enabled plugins
     */
    public function getPluginApiEndpoints(): array
    {
        $endpoints = [];
        
        $this->getEnabledPlugins()->each(function ($plugin) use (&$endpoints) {
            $pluginEndpoints = $plugin->getApiEndpoints();
            $endpoints = array_merge($endpoints, $pluginEndpoints);
        });
        
        return $endpoints;
    }

    /**
     * Get all settings from enabled plugins
     */
    public function getPluginSettings(): array
    {
        $settings = [];
        
        $this->getEnabledPlugins()->each(function ($plugin) use (&$settings) {
            $pluginSettings = $plugin->getSettings();
            $settings = array_merge($settings, $pluginSettings);
        });
        
        return $settings;
    }

    /**
     * Enable a plugin
     */
    public function enablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        
        if (!$plugin) {
            return false;
        }

        $plugin->enable();
        return true;
    }

    /**
     * Disable a plugin
     */
    public function disablePlugin(string $name): bool
    {
        $plugin = $this->getPlugin($name);
        
        if (!$plugin) {
            return false;
        }

        $plugin->disable();
        return true;
    }

    /**
     * Get plugin statistics
     */
    public function getPluginStats(): array
    {
        return [
            'total' => $this->plugins->count(),
            'enabled' => $this->getEnabledPlugins()->count(),
            'disabled' => $this->plugins->count() - $this->getEnabledPlugins()->count(),
        ];
    }
} 
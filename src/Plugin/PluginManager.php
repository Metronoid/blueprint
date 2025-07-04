<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginDiscovery;
use Blueprint\Contracts\PluginManager as PluginManagerContract;
use Blueprint\Events\PluginDiscovered;
use Blueprint\Events\PluginRegistered;
use Blueprint\Events\PluginBooted;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class PluginManager implements PluginManagerContract
{
    private array $plugins = [];
    private array $pluginConfigs = [];
    private bool $pluginsBooted = false;

    public function __construct(
        private PluginDiscovery $discovery,
        private Dispatcher $events
    ) {}

    public function registerPlugin(Plugin $plugin): void
    {
        $name = $plugin->getName();
        
        if ($this->hasPlugin($name)) {
            Log::warning("Plugin '{$name}' is already registered, skipping.");
            return;
        }

        $this->plugins[$name] = $plugin;
        $this->events->dispatch(new PluginRegistered($plugin));
        
        Log::info("Plugin '{$name}' registered successfully.");
    }

    public function getPlugins(): array
    {
        return $this->plugins;
    }

    public function getPlugin(string $name): ?Plugin
    {
        return $this->plugins[$name] ?? null;
    }

    public function hasPlugin(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    public function bootPlugins(): void
    {
        if ($this->pluginsBooted) {
            return;
        }

        foreach ($this->plugins as $plugin) {
            try {
                $plugin->boot();
                $this->events->dispatch(new PluginBooted($plugin));
                Log::info("Plugin '{$plugin->getName()}' booted successfully.");
            } catch (\Exception $e) {
                Log::error("Failed to boot plugin '{$plugin->getName()}': " . $e->getMessage());
            }
        }

        $this->pluginsBooted = true;
    }

    public function registerPluginServices(): void
    {
        foreach ($this->plugins as $plugin) {
            try {
                $plugin->register();
                Log::info("Plugin '{$plugin->getName()}' services registered successfully.");
            } catch (\Exception $e) {
                Log::error("Failed to register services for plugin '{$plugin->getName()}': " . $e->getMessage());
            }
        }
    }

    public function discoverPlugins(): void
    {
        try {
            $manifests = $this->discovery->discover();
            
            foreach ($manifests as $manifest) {
                $this->loadPluginFromManifest($manifest);
            }
        } catch (\Exception $e) {
            Log::error("Failed to discover plugins: " . $e->getMessage());
        }
    }

    public function getPluginConfig(string $pluginName): array
    {
        return $this->pluginConfigs[$pluginName] ?? [];
    }

    public function setPluginConfig(string $pluginName, array $config): void
    {
        $this->pluginConfigs[$pluginName] = $config;
    }

    /**
     * Load a plugin from its manifest.
     */
    private function loadPluginFromManifest(array $manifest): void
    {
        try {
            $className = $manifest['class'];
            
            if (!class_exists($className)) {
                Log::error("Plugin class '{$className}' not found for plugin '{$manifest['name']}'.");
                return;
            }

            $plugin = new $className();
            
            if (!$plugin instanceof Plugin) {
                Log::error("Plugin class '{$className}' does not implement Plugin interface.");
                return;
            }

            // Check compatibility
            $blueprintVersion = $this->getBlueprintVersion();
            if (!$plugin->isCompatible($blueprintVersion)) {
                Log::warning("Plugin '{$plugin->getName()}' is not compatible with Blueprint version {$blueprintVersion}.");
                return;
            }

            // Check dependencies
            if (!$this->checkDependencies($plugin)) {
                Log::warning("Plugin '{$plugin->getName()}' has unmet dependencies.");
                return;
            }

            // Store plugin configuration
            if (isset($manifest['config'])) {
                $this->setPluginConfig($plugin->getName(), $manifest['config']);
            }

            $this->registerPlugin($plugin);
            $this->events->dispatch(new PluginDiscovered($plugin, $manifest['discovery_method'] ?? 'unknown'));
            
        } catch (\Exception $e) {
            Log::error("Failed to load plugin from manifest: " . $e->getMessage());
        }
    }

    /**
     * Check if plugin dependencies are satisfied.
     */
    private function checkDependencies(Plugin $plugin): bool
    {
        $dependencies = $plugin->getDependencies();
        
        foreach ($dependencies as $dependency => $version) {
            // For now, we'll just check if the dependency is installed
            // In a more advanced implementation, we'd check version constraints
            if (str_starts_with($dependency, 'blueprint/')) {
                // Blueprint plugin dependency
                $pluginName = str_replace('blueprint/', '', $dependency);
                if (!$this->hasPlugin($pluginName)) {
                    return false;
                }
            } else {
                // Composer package dependency
                if (!$this->isComposerPackageInstalled($dependency)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a composer package is installed.
     */
    private function isComposerPackageInstalled(string $package): bool
    {
        $vendorPath = base_path('vendor/' . $package);
        return is_dir($vendorPath);
    }

    /**
     * Get the current Blueprint version.
     */
    private function getBlueprintVersion(): string
    {
        // Try to get version from composer.json
        $composerPath = __DIR__ . '/../../composer.json';
        if (file_exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            if (isset($composerData['version'])) {
                return $composerData['version'];
            }
        }

        // Fallback to a default version
        return '1.0.0';
    }

    /**
     * Get plugin statistics.
     */
    public function getStats(): array
    {
        return [
            'total_plugins' => count($this->plugins),
            'booted_plugins' => $this->pluginsBooted ? count($this->plugins) : 0,
            'plugin_names' => array_keys($this->plugins),
        ];
    }

    /**
     * Enable a plugin.
     */
    public function enablePlugin(string $name): bool
    {
        if (!$this->hasPlugin($name)) {
            return false;
        }

        // For now, plugins are enabled by default when registered
        // In a more advanced implementation, we'd have enable/disable state
        return true;
    }

    /**
     * Disable a plugin.
     */
    public function disablePlugin(string $name): bool
    {
        if (!$this->hasPlugin($name)) {
            return false;
        }

        // For now, we'll just log the action
        // In a more advanced implementation, we'd actually disable the plugin
        Log::info("Plugin '{$name}' disabled (not fully implemented yet).");
        return true;
    }
} 
<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginDiscovery;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Contracts\PluginManager as PluginManagerContract;
use Blueprint\Events\PluginDiscovered;
use Blueprint\Events\PluginRegistered;
use Blueprint\Events\PluginBooted;
use Blueprint\Exceptions\ValidationException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class PluginManager implements PluginManagerContract
{
    private array $plugins = [];
    private array $pluginConfigs = [];
    private bool $pluginsBooted = false;
    private ?GeneratorRegistry $generatorRegistry = null;
    private ?ConfigValidator $configValidator = null;
    private ?LoadOrderManager $loadOrderManager = null;

    public function __construct(
        private PluginDiscovery $discovery,
        private Dispatcher $events
    ) {
        // Initialize dependency management
        $dependencyResolver = new DependencyResolver();
        $this->loadOrderManager = new LoadOrderManager($dependencyResolver);
    }

    public function registerPlugin(Plugin $plugin): void
    {
        $name = $plugin->getName();
        
        if ($this->hasPlugin($name)) {
            $this->safeLog('warning', "Plugin '{$name}' is already registered, skipping.");
            return;
        }

        $this->plugins[$name] = $plugin;
        
        // Add to load order manager
        if ($this->loadOrderManager) {
            $this->loadOrderManager->addPlugin($plugin);
        }
        
        // Register plugin generators if available
        if ($this->generatorRegistry && method_exists($plugin, 'getGenerators')) {
            $generators = $plugin->getGenerators();
            foreach ($generators as $generator) {
                if ($generator instanceof PluginGenerator) {
                    $this->generatorRegistry->registerPluginGenerator($generator);
                    $this->safeLog('info', "Registered generator '{$generator->getName()}' from plugin '{$name}'.");
                }
            }
        }
        
        $this->events->dispatch(new PluginRegistered($plugin));
        
        $this->safeLog('info', "Plugin '{$name}' registered successfully.");
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

        // Use load order manager to get boot order, but boot plugins directly
        if ($this->loadOrderManager) {
            try {
                $loadOrder = $this->loadOrderManager->calculateLoadOrder();
                
                foreach ($loadOrder as $plugin) {
                    try {
                        $plugin->boot();
                        $this->events->dispatch(new PluginBooted($plugin));
                        $this->safeLog('info', "Plugin '{$plugin->getName()}' booted successfully.");
                    } catch (\Exception $e) {
                        $this->safeLog('error', "Failed to boot plugin '{$plugin->getName()}': " . $e->getMessage());
                    }
                }
                
            } catch (\Exception $e) {
                $this->safeLog('error', "Failed to boot plugins using load order manager: " . $e->getMessage());
                // Fallback to original boot method
                $this->bootPluginsLegacy();
            }
        } else {
            $this->bootPluginsLegacy();
        }

        $this->pluginsBooted = true;
    }

    /**
     * Legacy boot method (fallback).
     */
    private function bootPluginsLegacy(): void
    {
        foreach ($this->plugins as $plugin) {
            try {
                $plugin->boot();
                $this->events->dispatch(new PluginBooted($plugin));
                $this->safeLog('info', "Plugin '{$plugin->getName()}' booted successfully.");
            } catch (\Exception $e) {
                $this->safeLog('error', "Failed to boot plugin '{$plugin->getName()}': " . $e->getMessage());
            }
        }
    }

    public function registerPluginServices(): void
    {
        // Use load order manager to register services in correct order
        if ($this->loadOrderManager) {
            try {
                $loadOrder = $this->loadOrderManager->calculateLoadOrder();
                
                foreach ($loadOrder as $plugin) {
                    try {
                        $plugin->register();
                        $this->safeLog('info', "Plugin '{$plugin->getName()}' services registered successfully.");
                    } catch (\Exception $e) {
                        $this->safeLog('error', "Failed to register services for plugin '{$plugin->getName()}': " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                $this->safeLog('error', "Failed to calculate load order for service registration: " . $e->getMessage());
                // Fallback to original method
                $this->registerPluginServicesLegacy();
            }
        } else {
            $this->registerPluginServicesLegacy();
        }
    }

    /**
     * Legacy service registration method (fallback).
     */
    private function registerPluginServicesLegacy(): void
    {
        foreach ($this->plugins as $plugin) {
            try {
                $plugin->register();
                $this->safeLog('info', "Plugin '{$plugin->getName()}' services registered successfully.");
            } catch (\Exception $e) {
                $this->safeLog('error', "Failed to register services for plugin '{$plugin->getName()}': " . $e->getMessage());
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
            $this->safeLog('error', "Failed to discover plugins: " . $e->getMessage());
        }
    }

    public function getPluginConfig(string $pluginName): array
    {
        return $this->pluginConfigs[$pluginName] ?? [];
    }

    public function setPluginConfig(string $pluginName, array $config): void
    {
        // Validate configuration if validator is available
        if ($this->configValidator) {
            try {
                $config = $this->configValidator->validate($pluginName, $config);
            } catch (ValidationException $e) {
                $this->safeLog('error', "Plugin '{$pluginName}' configuration validation failed: " . $e->getMessage());
                throw $e;
            }
        }
        
        $this->pluginConfigs[$pluginName] = $config;
    }

    /**
     * Set the generator registry for managing plugin generators.
     */
    public function setGeneratorRegistry(GeneratorRegistry $registry): void
    {
        $this->generatorRegistry = $registry;
    }

    /**
     * Get the generator registry.
     */
    public function getGeneratorRegistry(): ?GeneratorRegistry
    {
        return $this->generatorRegistry;
    }

    /**
     * Set the configuration validator.
     */
    public function setConfigValidator(ConfigValidator $validator): void
    {
        $this->configValidator = $validator;
    }

    /**
     * Get the configuration validator.
     */
    public function getConfigValidator(): ?ConfigValidator
    {
        return $this->configValidator;
    }

    /**
     * Register a configuration schema for a plugin.
     */
    public function registerConfigSchema(string $pluginName, array $schema): void
    {
        if ($this->configValidator) {
            $this->configValidator->registerSchema($pluginName, $schema);
        }
    }

    /**
     * Load a plugin from its manifest.
     */
    private function loadPluginFromManifest(array $manifest): void
    {
        try {
            $className = $manifest['class'];
            
            if (!class_exists($className)) {
                $this->safeLog('error', "Plugin class '{$className}' not found for plugin '{$manifest['name']}'.");
                return;
            }

            $plugin = new $className();
            
            if (!$plugin instanceof Plugin) {
                $this->safeLog('error', "Plugin class '{$className}' does not implement Plugin interface.");
                return;
            }

            // Check compatibility
            $blueprintVersion = $this->getBlueprintVersion();
            if (!$plugin->isCompatible($blueprintVersion)) {
                $this->safeLog('warning', "Plugin '{$plugin->getName()}' is not compatible with Blueprint version {$blueprintVersion}.");
                return;
            }

            // Check dependencies
            if (!$this->checkDependencies($plugin)) {
                $this->safeLog('warning', "Plugin '{$plugin->getName()}' has unmet dependencies.");
                return;
            }

            // Store plugin configuration
            if (isset($manifest['config'])) {
                $this->setPluginConfig($plugin->getName(), $manifest['config']);
            }

            $this->registerPlugin($plugin);
            $this->events->dispatch(new PluginDiscovered($plugin, $manifest['discovery_method'] ?? 'unknown'));
            
        } catch (\Exception $e) {
            $this->safeLog('error', "Failed to load plugin from manifest: " . $e->getMessage());
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
        $stats = [
            'total_plugins' => count($this->plugins),
            'booted_plugins' => $this->pluginsBooted ? count($this->plugins) : 0,
            'plugin_names' => array_keys($this->plugins),
        ];

        // Add load order manager stats if available
        if ($this->loadOrderManager) {
            $stats['load_order_stats'] = $this->loadOrderManager->getStats();
        }

        return $stats;
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
                    $this->safeLog('info', "Plugin '{$name}' disabled (not fully implemented yet).");
        return true;
    }

    /**
     * Get the load order manager.
     */
    public function getLoadOrderManager(): ?LoadOrderManager
    {
        return $this->loadOrderManager;
    }

    /**
     * Get plugin load order.
     */
    public function getPluginLoadOrder(): array
    {
        return $this->loadOrderManager ? $this->loadOrderManager->getLoadOrderNames() : [];
    }

    /**
     * Get plugin dependency tree.
     */
    public function getPluginDependencyTree(string $pluginName): array
    {
        if (!$this->loadOrderManager) {
            return [];
        }

        return $this->loadOrderManager->getDependencyResolver()->getDependencyTree($pluginName);
    }

    /**
     * Check if plugin dependencies are satisfied.
     */
    public function arePluginDependenciesSatisfied(string $pluginName): bool
    {
        if (!$this->loadOrderManager) {
            return true; // Assume satisfied if no dependency management
        }

        return $this->loadOrderManager->getDependencyResolver()->areDependenciesSatisfied($pluginName);
    }

    /**
     * Get missing dependencies for a plugin.
     */
    public function getPluginMissingDependencies(string $pluginName): array
    {
        if (!$this->loadOrderManager) {
            return [];
        }

        return $this->loadOrderManager->getDependencyResolver()->getMissingDependencies($pluginName);
    }

    /**
     * Safe logging method that doesn't throw exceptions if Log facade is not available.
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            if (class_exists('Illuminate\Support\Facades\Log') && method_exists(Log::class, $level)) {
                Log::{$level}($message, $context);
            }
        } catch (\Exception $e) {
            // Silently ignore logging errors in test environment
        }
    }
} 
<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;
use Blueprint\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;

class LoadOrderManager
{
    protected DependencyResolver $dependencyResolver;
    protected array $loadOrder = [];
    protected array $loadedPlugins = [];
    protected array $priorities = [];
    protected bool $skipDependencyValidation = false;

    public function __construct(DependencyResolver $dependencyResolver)
    {
        $this->dependencyResolver = $dependencyResolver;
    }

    /**
     * Safe logging that won't fail if Log facade is not available.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        try {
            if (class_exists('Illuminate\Support\Facades\Log') && app()->bound('log')) {
                Log::$level($message, $context);
            }
        } catch (\Exception $e) {
            // Silently ignore logging errors in test environments
        }
    }

    /**
     * Add a plugin to the load order manager.
     */
    public function addPlugin(Plugin $plugin, int $priority = 0): void
    {
        $name = $plugin->getName();
        $this->dependencyResolver->addPlugin($plugin);
        $this->priorities[$name] = $priority;
        
        // Clear cached load order
        $this->loadOrder = [];
    }

    /**
     * Remove a plugin from the load order manager.
     */
    public function removePlugin(string $name): void
    {
        $this->dependencyResolver->removePlugin($name);
        unset($this->priorities[$name]);
        
        // Remove from loaded plugins
        $this->loadedPlugins = array_filter($this->loadedPlugins, fn($plugin) => $plugin->getName() !== $name);
        
        // Clear cached load order
        $this->loadOrder = [];
    }

    /**
     * Calculate and return the optimal load order.
     */
    public function calculateLoadOrder(): array
    {
        if (!empty($this->loadOrder)) {
            return $this->loadOrder;
        }

        try {
            // Get dependency-resolved order
            $dependencyOrder = $this->dependencyResolver->resolve();
            
            // Apply priority sorting within dependency constraints
            $this->loadOrder = $this->applyPrioritySorting($dependencyOrder);
            
            $this->log('info', 'Plugin load order calculated successfully', [
                'order' => array_map(fn($plugin) => $plugin->getName(), $this->loadOrder)
            ]);
            
            return $this->loadOrder;
            
        } catch (ValidationException $e) {
            $this->log('error', 'Failed to calculate plugin load order: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the current load order.
     */
    public function getLoadOrder(): array
    {
        return $this->loadOrder;
    }

    /**
     * Get the names of plugins in load order.
     */
    public function getLoadOrderNames(): array
    {
        return array_map(fn($plugin) => $plugin->getName(), $this->loadOrder);
    }

    /**
     * Load plugins in the correct order.
     */
    public function loadPlugins(): array
    {
        $loadOrder = $this->calculateLoadOrder();
        $loaded = [];
        $failed = [];

        foreach ($loadOrder as $plugin) {
            try {
                $this->loadPlugin($plugin);
                $loaded[] = $plugin->getName();
                $this->log('info', "Plugin '{$plugin->getName()}' loaded successfully");
            } catch (\Exception $e) {
                $failed[] = [
                    'plugin' => $plugin->getName(),
                    'error' => $e->getMessage()
                ];
                $this->log('error', "Failed to load plugin '{$plugin->getName()}': " . $e->getMessage());
            }
        }

        return [
            'loaded' => $loaded,
            'failed' => $failed,
            'total' => count($loadOrder)
        ];
    }

    /**
     * Load a single plugin.
     */
    protected function loadPlugin(Plugin $plugin): void
    {
        $name = $plugin->getName();
        
        // Check if already loaded
        if ($this->isPluginLoaded($name)) {
            return;
        }

        // Check dependencies are loaded
        $this->validateDependenciesLoaded($plugin);

        // Register plugin services
        $plugin->register();

        // Boot plugin
        $plugin->boot();

        // Mark as loaded
        $this->loadedPlugins[] = $plugin;
    }

    /**
     * Check if a plugin is loaded.
     */
    public function isPluginLoaded(string $name): bool
    {
        foreach ($this->loadedPlugins as $plugin) {
            if ($plugin->getName() === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get loaded plugins.
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
    }

    /**
     * Get loaded plugin names.
     */
    public function getLoadedPluginNames(): array
    {
        return array_map(fn($plugin) => $plugin->getName(), $this->loadedPlugins);
    }

    /**
     * Validate that all dependencies for a plugin are loaded.
     */
    protected function validateDependenciesLoaded(Plugin $plugin): void
    {
        if ($this->skipDependencyValidation) {
            return;
        }

        $dependencies = $plugin->getDependencies();
        $loadedNames = $this->getLoadedPluginNames();

        foreach ($dependencies as $dependency => $version) {
            // Only check blueprint plugin dependencies
            if (str_starts_with($dependency, 'blueprint/')) {
                $depName = str_replace('blueprint/', '', $dependency);
                if (!in_array($depName, $loadedNames)) {
                    throw new ValidationException(
                        "Plugin '{$plugin->getName()}' cannot be loaded: dependency '{$depName}' is not loaded"
                    );
                }
            }
        }
    }

    /**
     * Apply priority sorting within dependency constraints.
     */
    protected function applyPrioritySorting(array $dependencyOrder): array
    {
        // Group plugins by their dependency level
        $levels = $this->groupByDependencyLevel($dependencyOrder);
        
        $sorted = [];
        foreach ($levels as $level) {
            // Sort each level by priority
            usort($level, function (Plugin $a, Plugin $b) {
                $priorityA = $this->priorities[$a->getName()] ?? 0;
                $priorityB = $this->priorities[$b->getName()] ?? 0;
                
                // Higher priority first
                return $priorityB <=> $priorityA;
            });
            
            $sorted = array_merge($sorted, $level);
        }
        
        return $sorted;
    }

    /**
     * Group plugins by their dependency level.
     */
    protected function groupByDependencyLevel(array $plugins): array
    {
        $levels = [];
        $processed = [];
        
        foreach ($plugins as $plugin) {
            $level = $this->calculateDependencyLevel($plugin, $processed);
            
            if (!isset($levels[$level])) {
                $levels[$level] = [];
            }
            
            $levels[$level][] = $plugin;
            $processed[$plugin->getName()] = $level;
        }
        
        ksort($levels);
        return $levels;
    }

    /**
     * Calculate the dependency level for a plugin.
     */
    protected function calculateDependencyLevel(Plugin $plugin, array $processed): int
    {
        $dependencies = $plugin->getDependencies();
        $maxLevel = 0;
        
        foreach ($dependencies as $dependency => $version) {
            if (str_starts_with($dependency, 'blueprint/')) {
                $depName = str_replace('blueprint/', '', $dependency);
                
                if (isset($processed[$depName])) {
                    $maxLevel = max($maxLevel, $processed[$depName] + 1);
                }
            }
        }
        
        return $maxLevel;
    }

    /**
     * Get plugins that can be loaded (all dependencies satisfied).
     */
    public function getLoadablePlugins(): array
    {
        if (empty($this->loadOrder)) {
            $this->calculateLoadOrder();
        }
        
        $loadable = [];
        
        foreach ($this->loadOrder as $plugin) {
            if (!$this->isPluginLoaded($plugin->getName())) {
                // Check if all dependencies are loaded
                $canLoad = true;
                foreach ($plugin->getDependencies() as $dependency => $version) {
                    if (str_starts_with($dependency, 'blueprint/')) {
                        $depName = str_replace('blueprint/', '', $dependency);
                        if (!$this->isPluginLoaded($depName)) {
                            $canLoad = false;
                            break;
                        }
                    }
                }
                
                if ($canLoad) {
                    $loadable[] = $plugin;
                }
            }
        }
        
        return $loadable;
    }

    /**
     * Get plugins that are blocked by dependencies.
     */
    public function getBlockedPlugins(): array
    {
        if (empty($this->loadOrder)) {
            $this->calculateLoadOrder();
        }
        
        $blocked = [];
        
        foreach ($this->loadOrder as $plugin) {
            if (!$this->isPluginLoaded($plugin->getName())) {
                // Check if any dependencies are not loaded
                $missingDeps = [];
                foreach ($plugin->getDependencies() as $dependency => $version) {
                    if (str_starts_with($dependency, 'blueprint/')) {
                        $depName = str_replace('blueprint/', '', $dependency);
                        if (!$this->isPluginLoaded($depName)) {
                            $missingDeps[] = $depName;
                        }
                    }
                }
                
                if (!empty($missingDeps)) {
                    $blocked[] = [
                        'plugin' => $plugin,
                        'missing_dependencies' => $missingDeps
                    ];
                }
            }
        }
        
        return $blocked;
    }

    /**
     * Get load order statistics.
     */
    public function getStats(): array
    {
        if (empty($this->loadOrder)) {
            $this->calculateLoadOrder();
        }
        
        $dependencyStats = $this->dependencyResolver->getStats();
        
        return [
            'total_plugins' => count($this->loadOrder),
            'loaded_plugins' => count($this->loadedPlugins),
            'pending_plugins' => count($this->loadOrder) - count($this->loadedPlugins),
            'loadable_plugins' => count($this->getLoadablePlugins()),
            'blocked_plugins' => count($this->getBlockedPlugins()),
            'load_order_calculated' => !empty($this->loadOrder),
            'dependency_stats' => $dependencyStats,
            'load_order' => $this->getLoadOrderNames(),
            'loaded_plugin_names' => $this->getLoadedPluginNames(),
        ];
    }

    /**
     * Reset the load order manager.
     */
    public function reset(): void
    {
        $this->loadOrder = [];
        $this->loadedPlugins = [];
    }

    /**
     * Get the dependency resolver.
     */
    public function getDependencyResolver(): DependencyResolver
    {
        return $this->dependencyResolver;
    }

    /**
     * Check if all plugins are loaded.
     */
    public function areAllPluginsLoaded(): bool
    {
        if (empty($this->loadOrder)) {
            $this->calculateLoadOrder();
        }
        
        return count($this->loadedPlugins) === count($this->loadOrder);
    }

    /**
     * Get the next plugin to load.
     */
    public function getNextPluginToLoad(): ?Plugin
    {
        $loadable = $this->getLoadablePlugins();
        return $loadable[0] ?? null;
    }

    /**
     * Force load a plugin (skip dependency checks).
     */
    public function forceLoadPlugin(Plugin $plugin): void
    {
        try {
            // Temporarily disable dependency validation
            $originalValidation = $this->skipDependencyValidation ?? false;
            $this->skipDependencyValidation = true;
            
            $this->loadPlugin($plugin);
            
            $this->skipDependencyValidation = $originalValidation;
            $this->log('warning', "Plugin '{$plugin->getName()}' was force-loaded, skipping dependency validation");
        } catch (\Exception $e) {
            $this->log('error', "Failed to force-load plugin '{$plugin->getName()}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Unload a plugin.
     */
    public function unloadPlugin(string $name): bool
    {
        $plugin = null;
        $index = null;
        
        foreach ($this->loadedPlugins as $i => $loadedPlugin) {
            if ($loadedPlugin->getName() === $name) {
                $plugin = $loadedPlugin;
                $index = $i;
                break;
            }
        }
        
        if ($plugin === null) {
            return false;
        }
        
        // Check if other plugins depend on this one
        $reverseDeps = $this->dependencyResolver->getReverseDependencies($name);
        $loadedReverseDeps = array_intersect($reverseDeps, $this->getLoadedPluginNames());
        
        if (!empty($loadedReverseDeps)) {
            throw new ValidationException(
                "Cannot unload plugin '{$name}': it is required by loaded plugins: " . implode(', ', $loadedReverseDeps)
            );
        }
        
        // Remove from loaded plugins
        array_splice($this->loadedPlugins, $index, 1);
        
        $this->log('info', "Plugin '{$name}' unloaded successfully");
        return true;
    }
} 
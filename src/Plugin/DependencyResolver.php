<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;
use Blueprint\Exceptions\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DependencyResolver
{
    protected array $plugins = [];
    protected array $dependencyGraph = [];
    protected array $loadOrder = [];
    protected array $resolved = [];
    protected array $resolving = [];

    /**
     * Add a plugin to the resolver.
     */
    public function addPlugin(Plugin $plugin): void
    {
        $name = $plugin->getName();
        $this->plugins[$name] = $plugin;
        $this->dependencyGraph[$name] = $plugin->getDependencies();
    }

    /**
     * Remove a plugin from the resolver.
     */
    public function removePlugin(string $name): void
    {
        unset($this->plugins[$name]);
        unset($this->dependencyGraph[$name]);
        
        // Remove from load order if present
        $this->loadOrder = array_filter($this->loadOrder, fn($pluginName) => $pluginName !== $name);
        
        // Clear resolution cache
        $this->resolved = [];
        $this->resolving = [];
    }

    /**
     * Resolve dependencies and return plugins in load order.
     */
    public function resolve(): array
    {
        $this->loadOrder = [];
        $this->resolved = [];
        $this->resolving = [];

        // First pass: validate all dependencies exist and versions are compatible
        $this->validateDependencies();

        // Second pass: resolve load order
        foreach (array_keys($this->plugins) as $pluginName) {
            $this->resolveDependencies($pluginName);
        }

        // Return plugins in resolved order
        return array_map(fn($name) => $this->plugins[$name], $this->loadOrder);
    }

    /**
     * Get the load order of plugins.
     */
    public function getLoadOrder(): array
    {
        return $this->loadOrder;
    }

    /**
     * Check if dependencies are satisfied for a plugin.
     */
    public function areDependenciesSatisfied(string $pluginName): bool
    {
        if (!isset($this->plugins[$pluginName])) {
            return false;
        }

        $dependencies = $this->dependencyGraph[$pluginName];

        foreach ($dependencies as $dependency => $versionConstraint) {
            if (!$this->isDependencySatisfied($dependency, $versionConstraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing dependencies for a plugin.
     */
    public function getMissingDependencies(string $pluginName): array
    {
        if (!isset($this->plugins[$pluginName])) {
            return [];
        }

        $missing = [];
        $dependencies = $this->dependencyGraph[$pluginName];

        foreach ($dependencies as $dependency => $versionConstraint) {
            if (!$this->isDependencySatisfied($dependency, $versionConstraint)) {
                $missing[] = [
                    'name' => $dependency,
                    'constraint' => $versionConstraint,
                    'reason' => $this->getDependencyFailureReason($dependency, $versionConstraint)
                ];
            }
        }

        return $missing;
    }

    /**
     * Check for circular dependencies.
     */
    public function hasCircularDependencies(): bool
    {
        foreach (array_keys($this->plugins) as $pluginName) {
            if ($this->hasCircularDependency($pluginName, [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get circular dependency chains.
     */
    public function getCircularDependencies(): array
    {
        $circular = [];

        foreach (array_keys($this->plugins) as $pluginName) {
            $chain = $this->getCircularDependencyChain($pluginName, []);
            if (!empty($chain)) {
                $circular[] = $chain;
            }
        }

        return array_unique($circular, SORT_REGULAR);
    }

    /**
     * Get dependency tree for a plugin.
     */
    public function getDependencyTree(string $pluginName): array
    {
        if (!isset($this->plugins[$pluginName])) {
            return [];
        }

        $tree = [];
        $dependencies = $this->dependencyGraph[$pluginName];

        foreach ($dependencies as $dependency => $versionConstraint) {
            $tree[$dependency] = [
                'constraint' => $versionConstraint,
                'satisfied' => $this->isDependencySatisfied($dependency, $versionConstraint),
                'children' => $this->isDependencySatisfied($dependency, $versionConstraint) 
                    ? $this->getDependencyTree($dependency) 
                    : []
            ];
        }

        return $tree;
    }

    /**
     * Get reverse dependencies (plugins that depend on this plugin).
     */
    public function getReverseDependencies(string $pluginName): array
    {
        $reverse = [];

        foreach ($this->dependencyGraph as $plugin => $dependencies) {
            foreach ($dependencies as $dependency => $constraint) {
                // Check for blueprint plugin dependencies
                if (Str::startsWith($dependency, 'blueprint/')) {
                    $depPluginName = Str::after($dependency, 'blueprint/');
                    if ($depPluginName === $pluginName) {
                        $reverse[] = $plugin;
                        break;
                    }
                }
                // Check for direct plugin name dependencies
                if ($dependency === $pluginName) {
                    $reverse[] = $plugin;
                    break;
                }
            }
        }

        return $reverse;
    }

    /**
     * Validate all dependencies exist and versions are compatible.
     */
    protected function validateDependencies(): void
    {
        $errors = [];

        foreach ($this->plugins as $pluginName => $plugin) {
            $dependencies = $this->dependencyGraph[$pluginName];

            foreach ($dependencies as $dependency => $versionConstraint) {
                if (!$this->isDependencySatisfied($dependency, $versionConstraint)) {
                    $reason = $this->getDependencyFailureReason($dependency, $versionConstraint);
                    $errors[] = "Plugin '{$pluginName}' has unmet dependency '{$dependency}' {$versionConstraint}: {$reason}";
                }
            }
        }

        // Check for circular dependencies
        if ($this->hasCircularDependencies()) {
            $circular = $this->getCircularDependencies();
            foreach ($circular as $chain) {
                $errors[] = "Circular dependency detected: " . implode(' -> ', $chain);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException("Dependency validation failed:\n" . implode("\n", $errors));
        }
    }

    /**
     * Recursively resolve dependencies for a plugin.
     */
    protected function resolveDependencies(string $pluginName): void
    {
        // Already resolved
        if (in_array($pluginName, $this->resolved)) {
            return;
        }

        // Circular dependency check
        if (in_array($pluginName, $this->resolving)) {
            throw new ValidationException("Circular dependency detected involving plugin '{$pluginName}'");
        }

        $this->resolving[] = $pluginName;

        // Resolve dependencies first
        $dependencies = $this->dependencyGraph[$pluginName] ?? [];
        foreach (array_keys($dependencies) as $dependency) {
            // Handle blueprint plugin dependencies
            if (Str::startsWith($dependency, 'blueprint/')) {
                $depPluginName = Str::after($dependency, 'blueprint/');
                if (isset($this->plugins[$depPluginName])) {
                    $this->resolveDependencies($depPluginName);
                }
            } elseif (isset($this->plugins[$dependency])) {
                // Handle direct plugin name dependencies
                $this->resolveDependencies($dependency);
            }
        }

        // Add to load order
        if (!in_array($pluginName, $this->loadOrder)) {
            $this->loadOrder[] = $pluginName;
        }

        // Mark as resolved
        $this->resolved[] = $pluginName;
        $this->resolving = array_filter($this->resolving, fn($name) => $name !== $pluginName);
    }

    /**
     * Check if a single dependency is satisfied.
     */
    protected function isDependencySatisfied(string $dependency, string $versionConstraint): bool
    {
        // Blueprint plugin dependency
        if (Str::startsWith($dependency, 'blueprint/')) {
            $pluginName = Str::after($dependency, 'blueprint/');
            
            if (!isset($this->plugins[$pluginName])) {
                return false;
            }

            $plugin = $this->plugins[$pluginName];
            return $this->isVersionCompatible($plugin->getVersion(), $versionConstraint);
        }

        // Composer package dependency
        if (Str::contains($dependency, '/')) {
            return $this->isComposerPackageInstalled($dependency, $versionConstraint);
        }

        // System dependency (PHP version, extensions, etc.)
        if ($dependency === 'php') {
            return $this->isVersionCompatible(PHP_VERSION, $versionConstraint);
        }

        // Extension dependency
        if (Str::startsWith($dependency, 'ext-')) {
            $extension = Str::after($dependency, 'ext-');
            return extension_loaded($extension);
        }

        return false;
    }

    /**
     * Get the reason why a dependency failed.
     */
    protected function getDependencyFailureReason(string $dependency, string $versionConstraint): string
    {
        // Blueprint plugin dependency
        if (Str::startsWith($dependency, 'blueprint/')) {
            $pluginName = Str::after($dependency, 'blueprint/');
            
            if (!isset($this->plugins[$pluginName])) {
                return "Plugin '{$pluginName}' not found";
            }

            $plugin = $this->plugins[$pluginName];
            if (!$this->isVersionCompatible($plugin->getVersion(), $versionConstraint)) {
                return "Version mismatch: found {$plugin->getVersion()}, required {$versionConstraint}";
            }
        }

        // Composer package dependency
        if (Str::contains($dependency, '/')) {
            if (!$this->isComposerPackageInstalled($dependency)) {
                return "Composer package not installed";
            }
            
            $installedVersion = $this->getComposerPackageVersion($dependency);
            if ($installedVersion && !$this->isVersionCompatible($installedVersion, $versionConstraint)) {
                return "Version mismatch: found {$installedVersion}, required {$versionConstraint}";
            }
        }

        // System dependency
        if ($dependency === 'php') {
            return "PHP version mismatch: found " . PHP_VERSION . ", required {$versionConstraint}";
        }

        // Extension dependency
        if (Str::startsWith($dependency, 'ext-')) {
            $extension = Str::after($dependency, 'ext-');
            return "PHP extension '{$extension}' not loaded";
        }

        return "Unknown dependency type";
    }

    /**
     * Check if a composer package is installed.
     */
    protected function isComposerPackageInstalled(string $package, string $versionConstraint = null): bool
    {
        $vendorPath = base_path('vendor/' . $package);
        
        if (!is_dir($vendorPath)) {
            return false;
        }

        if ($versionConstraint) {
            $installedVersion = $this->getComposerPackageVersion($package);
            return $installedVersion && $this->isVersionCompatible($installedVersion, $versionConstraint);
        }

        return true;
    }

    /**
     * Get the version of an installed composer package.
     */
    protected function getComposerPackageVersion(string $package): ?string
    {
        $composerPath = base_path('vendor/' . $package . '/composer.json');
        
        if (!file_exists($composerPath)) {
            return null;
        }

        $composerData = json_decode(file_get_contents($composerPath), true);
        return $composerData['version'] ?? null;
    }

    /**
     * Check if a version satisfies a constraint.
     */
    protected function isVersionCompatible(string $version, string $constraint): bool
    {
        // Simple version comparison - in a real implementation, you'd use composer/semver
        if ($constraint === '*') {
            return true;
        }

        // Exact version
        if (!Str::contains($constraint, ['>', '<', '^', '~'])) {
            return version_compare($version, $constraint, '=');
        }

        // Caret constraint (^1.0 means >=1.0.0 <2.0.0)
        if (Str::startsWith($constraint, '^')) {
            $minVersion = Str::after($constraint, '^');
            $parts = explode('.', $minVersion);
            $majorVersion = $parts[0];
            $nextMajor = (int)$majorVersion + 1;
            
            return version_compare($version, $minVersion, '>=') && 
                   version_compare($version, $nextMajor . '.0.0', '<');
        }

        // Tilde constraint (~1.0 means >=1.0.0 <1.1.0)
        if (Str::startsWith($constraint, '~')) {
            $minVersion = Str::after($constraint, '~');
            $parts = explode('.', $minVersion);
            $majorVersion = $parts[0];
            $minorVersion = $parts[1] ?? '0';
            $nextMinor = (int)$minorVersion + 1;
            
            return version_compare($version, $minVersion, '>=') && 
                   version_compare($version, $majorVersion . '.' . $nextMinor . '.0', '<');
        }

        // Greater than
        if (Str::startsWith($constraint, '>=')) {
            $minVersion = Str::after($constraint, '>=');
            return version_compare($version, $minVersion, '>=');
        }

        if (Str::startsWith($constraint, '>')) {
            $minVersion = Str::after($constraint, '>');
            return version_compare($version, $minVersion, '>');
        }

        // Less than
        if (Str::startsWith($constraint, '<=')) {
            $maxVersion = Str::after($constraint, '<=');
            return version_compare($version, $maxVersion, '<=');
        }

        if (Str::startsWith($constraint, '<')) {
            $maxVersion = Str::after($constraint, '<');
            return version_compare($version, $maxVersion, '<');
        }

        return false;
    }

    /**
     * Check if a plugin has circular dependencies.
     */
    protected function hasCircularDependency(string $pluginName, array $visited): bool
    {
        if (in_array($pluginName, $visited)) {
            return true;
        }

        $visited[] = $pluginName;
        $dependencies = array_keys($this->dependencyGraph[$pluginName] ?? []);

        foreach ($dependencies as $dependency) {
            if (Str::startsWith($dependency, 'blueprint/')) {
                $depPluginName = Str::after($dependency, 'blueprint/');
                if (isset($this->plugins[$depPluginName]) && $this->hasCircularDependency($depPluginName, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get circular dependency chain.
     */
    protected function getCircularDependencyChain(string $pluginName, array $visited): array
    {
        if (in_array($pluginName, $visited)) {
            $circularStart = array_search($pluginName, $visited);
            return array_slice($visited, $circularStart);
        }

        $visited[] = $pluginName;
        $dependencies = array_keys($this->dependencyGraph[$pluginName] ?? []);

        foreach ($dependencies as $dependency) {
            if (Str::startsWith($dependency, 'blueprint/')) {
                $depPluginName = Str::after($dependency, 'blueprint/');
                if (isset($this->plugins[$depPluginName])) {
                    $chain = $this->getCircularDependencyChain($depPluginName, $visited);
                    if (!empty($chain)) {
                        return $chain;
                    }
                }
            }
        }

        return [];
    }

    /**
     * Get dependency resolution statistics.
     */
    public function getStats(): array
    {
        return [
            'total_plugins' => count($this->plugins),
            'total_dependencies' => array_sum(array_map('count', $this->dependencyGraph)),
            'plugins_with_dependencies' => count(array_filter($this->dependencyGraph, fn($deps) => !empty($deps))),
            'load_order_resolved' => !empty($this->loadOrder),
            'circular_dependencies' => $this->hasCircularDependencies(),
            'load_order' => $this->loadOrder,
        ];
    }
} 
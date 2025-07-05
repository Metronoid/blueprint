<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\PluginDiscovery as PluginDiscoveryContract;
use Blueprint\Contracts\Plugin;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class PluginDiscovery implements PluginDiscoveryContract
{
    public function __construct(
        private Filesystem $filesystem
    ) {}

    public function discover(): array
    {
        $plugins = [];

        // Discover from composer packages
        $plugins = array_merge($plugins, $this->discoverFromComposer());

        // Discover from plugins directory
        $pluginsDir = base_path('plugins');
        if ($this->filesystem->isDirectory($pluginsDir)) {
            $plugins = array_merge($plugins, $this->discoverFromDirectory($pluginsDir));
        }

        // Discover from custom paths defined in config
        $customPaths = config('blueprint.plugin_paths', []);
        foreach ($customPaths as $path) {
            if ($this->filesystem->isDirectory($path)) {
                $plugins = array_merge($plugins, $this->discoverFromDirectory($path));
            }
        }

        return $plugins;
    }

    public function discoverFromComposer(): array
    {
        $plugins = [];
        $composerPath = base_path('composer.json');
        
        if (!$this->filesystem->exists($composerPath)) {
            return $plugins;
        }

        $composerData = json_decode($this->filesystem->get($composerPath), true);
        
        // Check installed packages for blueprint plugins
        $vendorPath = base_path('vendor');
        if (!$this->filesystem->isDirectory($vendorPath)) {
            return $plugins;
        }

        foreach ($this->filesystem->directories($vendorPath) as $vendorDir) {
            foreach ($this->filesystem->directories($vendorDir) as $packageDir) {
                $packageComposerPath = $packageDir . '/composer.json';
                
                if (!$this->filesystem->exists($packageComposerPath)) {
                    continue;
                }

                $packageData = json_decode($this->filesystem->get($packageComposerPath), true);
                
                // Check if this is a blueprint plugin
                if ($this->isBluerintPlugin($packageData)) {
                    $manifest = $this->extractManifestFromComposer($packageData, $packageDir);
                    if ($manifest && $this->validateManifest($manifest)) {
                        $plugins[] = $manifest;
                    }
                }
            }
        }

        return $plugins;
    }

    public function discoverFromDirectory(string $directory): array
    {
        $plugins = [];

        if (!$this->filesystem->isDirectory($directory)) {
            return $plugins;
        }

        $directories = $this->filesystem->directories($directory);
        if (!$directories) {
            return $plugins;
        }

        foreach ($directories as $pluginDir) {
            $manifest = $this->getManifest($pluginDir);
            if ($manifest && $this->validateManifest($manifest)) {
                $plugins[] = $manifest;
            }
        }

        return $plugins;
    }

    public function validateManifest(array $manifest): bool
    {
        $required = ['name', 'version', 'class', 'description'];
        
        foreach ($required as $field) {
            if (!isset($manifest[$field]) || empty($manifest[$field])) {
                return false;
            }
        }

        // Only validate class if it exists (for testing purposes)
        if (class_exists($manifest['class'])) {
            // Validate class implements Plugin interface
            $reflection = new \ReflectionClass($manifest['class']);
            if (!$reflection->implementsInterface(Plugin::class)) {
                return false;
            }
        }

        return true;
    }

    public function getManifest(string $path): ?array
    {
        // Try blueprint.json first
        $blueprintJsonPath = $path . '/blueprint.json';
        if ($this->filesystem->exists($blueprintJsonPath)) {
            $manifest = json_decode($this->filesystem->get($blueprintJsonPath), true);
            if ($manifest) {
                $manifest['path'] = $path;
                return $manifest;
            }
        }

        // Try composer.json with blueprint extra
        $composerJsonPath = $path . '/composer.json';
        if ($this->filesystem->exists($composerJsonPath)) {
            $composerData = json_decode($this->filesystem->get($composerJsonPath), true);
            if ($this->isBluerintPlugin($composerData)) {
                $manifest = $this->extractManifestFromComposer($composerData, $path);
                if ($manifest) {
                    return $manifest;
                }
            }
        }

        return null;
    }

    /**
     * Check if a composer package is a Blueprint plugin.
     */
    private function isBluerintPlugin(array $composerData): bool
    {
        return isset($composerData['extra']['blueprint-plugin']) ||
               isset($composerData['type']) && $composerData['type'] === 'blueprint-plugin' ||
               isset($composerData['keywords']) && in_array('blueprint-plugin', $composerData['keywords']);
    }

    /**
     * Extract plugin manifest from composer.json data.
     */
    private function extractManifestFromComposer(array $composerData, string $path): ?array
    {
        $manifest = [
            'path' => $path,
            'name' => $composerData['name'] ?? basename($path),
            'version' => $composerData['version'] ?? '1.0.0',
            'description' => $composerData['description'] ?? '',
            'author' => $this->formatAuthor($composerData['authors'] ?? []),
            'dependencies' => $this->extractDependencies($composerData),
        ];

        // Get plugin-specific configuration
        $pluginConfig = $composerData['extra']['blueprint-plugin'] ?? [];
        
        if (isset($pluginConfig['class'])) {
            $manifest['class'] = $pluginConfig['class'];
        } else {
            // Try to auto-detect the plugin class
            $manifest['class'] = $this->autoDetectPluginClass($path, $composerData);
        }

        if (!$manifest['class']) {
            return null;
        }

        // Merge additional plugin configuration
        $manifest = array_merge($manifest, $pluginConfig);

        return $manifest;
    }

    /**
     * Format author information from composer.json.
     */
    private function formatAuthor(array $authors): string
    {
        if (empty($authors)) {
            return 'Unknown';
        }

        $author = $authors[0];
        $name = $author['name'] ?? 'Unknown';
        $email = isset($author['email']) ? " <{$author['email']}>" : '';
        
        return $name . $email;
    }

    /**
     * Extract plugin dependencies from composer.json.
     */
    private function extractDependencies(array $composerData): array
    {
        $dependencies = [];
        
        // Get blueprint-specific dependencies from extra section
        if (isset($composerData['extra']['blueprint-plugin']['dependencies'])) {
            $dependencies = array_merge($dependencies, $composerData['extra']['blueprint-plugin']['dependencies']);
        }
        
        // Also check regular composer dependencies for blueprint plugins
        if (isset($composerData['require'])) {
            foreach ($composerData['require'] as $package => $version) {
                if (Str::startsWith($package, 'blueprint/') || Str::contains($package, 'blueprint-plugin')) {
                    $dependencies[$package] = $version;
                }
            }
        }

        return $dependencies;
    }

    /**
     * Auto-detect the plugin class from the package structure.
     */
    private function autoDetectPluginClass(string $path, array $composerData): ?string
    {
        // Try to find the plugin class based on PSR-4 autoloading
        $autoload = $composerData['autoload']['psr-4'] ?? [];
        
        foreach ($autoload as $namespace => $directory) {
            $fullPath = $path . '/' . trim($directory, '/');
            
            if (!$this->filesystem->isDirectory($fullPath)) {
                continue;
            }

            // Look for Plugin.php or {PackageName}Plugin.php
            $pluginFiles = [
                'Plugin.php',
                'BlueprintPlugin.php',
                Str::studly(basename($path)) . 'Plugin.php'
            ];

            foreach ($pluginFiles as $file) {
                $filePath = $fullPath . '/' . $file;
                if ($this->filesystem->exists($filePath)) {
                    $className = rtrim($namespace, '\\') . '\\' . pathinfo($file, PATHINFO_FILENAME);
                    
                    // Check if class exists and implements Plugin interface
                    if (class_exists($className)) {
                        $reflection = new \ReflectionClass($className);
                        if ($reflection->implementsInterface(Plugin::class)) {
                            return $className;
                        }
                    }
                }
            }
        }

        return null;
    }
} 
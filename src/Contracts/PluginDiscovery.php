<?php

namespace Blueprint\Contracts;

interface PluginDiscovery
{
    /**
     * Discover plugins using the configured discovery methods.
     */
    public function discover(): array;

    /**
     * Discover plugins from composer packages.
     */
    public function discoverFromComposer(): array;

    /**
     * Discover plugins from a specific directory.
     */
    public function discoverFromDirectory(string $directory): array;

    /**
     * Validate a plugin manifest.
     */
    public function validateManifest(array $manifest): bool;

    /**
     * Get the plugin manifest from a path.
     */
    public function getManifest(string $path): ?array;
} 
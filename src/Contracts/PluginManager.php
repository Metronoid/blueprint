<?php

namespace Blueprint\Contracts;

interface PluginManager
{
    /**
     * Register a plugin.
     */
    public function registerPlugin(Plugin $plugin): void;

    /**
     * Get all registered plugins.
     */
    public function getPlugins(): array;

    /**
     * Get a plugin by name.
     */
    public function getPlugin(string $name): ?Plugin;

    /**
     * Check if a plugin is registered.
     */
    public function hasPlugin(string $name): bool;

    /**
     * Boot all registered plugins.
     */
    public function bootPlugins(): void;

    /**
     * Register services for all plugins.
     */
    public function registerPluginServices(): void;

    /**
     * Discover and register plugins automatically.
     */
    public function discoverPlugins(): void;

    /**
     * Get plugin configuration.
     */
    public function getPluginConfig(string $pluginName): array;

    /**
     * Set plugin configuration.
     */
    public function setPluginConfig(string $pluginName, array $config): void;
} 
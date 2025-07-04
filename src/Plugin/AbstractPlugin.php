<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;

abstract class AbstractPlugin implements Plugin
{
    protected string $name;
    protected string $version;
    protected string $description;
    protected string $author;
    protected array $dependencies = [];
    protected array $configSchema = [];

    public function getName(): string
    {
        return $this->name ?? class_basename(static::class);
    }

    public function getVersion(): string
    {
        return $this->version ?? '1.0.0';
    }

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function getAuthor(): string
    {
        return $this->author ?? 'Unknown';
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getConfigSchema(): array
    {
        return $this->configSchema;
    }

    public function boot(): void
    {
        // Default implementation - can be overridden
    }

    public function register(): void
    {
        // Default implementation - can be overridden
    }

    public function isCompatible(string $blueprintVersion): bool
    {
        // Default implementation - compatible with all versions
        // Override this method for specific version requirements
        return true;
    }

    /**
     * Get the plugin configuration.
     */
    protected function getConfig(): array
    {
        return app(\Blueprint\Contracts\PluginManager::class)->getPluginConfig($this->getName());
    }

    /**
     * Get a specific configuration value.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        $config = $this->getConfig();
        return data_get($config, $key, $default);
    }
} 
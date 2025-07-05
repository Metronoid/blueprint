<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;

abstract class AbstractPluginGenerator implements PluginGenerator
{
    protected Filesystem $filesystem;
    protected Plugin $plugin;
    protected array $config = [];
    protected int $priority = 100;
    protected array $types = [];

    public function __construct(Filesystem $files, Plugin $plugin = null)
    {
        $this->filesystem = $files;
        $this->plugin = $plugin;
    }

    /**
     * Get the plugin that provides this generator.
     */
    public function getPlugin(): Plugin
    {
        if ($this->plugin === null) {
            throw new \RuntimeException('Plugin not set for this generator');
        }
        return $this->plugin;
    }

    /**
     * Set the plugin for this generator.
     */
    public function setPlugin(Plugin $plugin): void
    {
        $this->plugin = $plugin;
    }

    /**
     * Get the generator priority (higher = runs first).
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set the generator priority.
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Check if this generator should run for the given tree.
     * Override this method to implement custom logic.
     */
    public function shouldRun(Tree $tree): bool
    {
        // Default: run if tree has any relevant content
        $types = $this->types();
        
        if (in_array('models', $types) && !empty($tree->models())) {
            return true;
        }
        
        if (in_array('controllers', $types) && !empty($tree->controllers())) {
            return true;
        }
        
        if (in_array('seeders', $types) && !empty($tree->seeders())) {
            return true;
        }

        return false;
    }

    /**
     * Get the generator configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the generator configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get a configuration value.
     */
    protected function config(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }

    /**
     * Get the types this generator handles.
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * Set the types this generator handles.
     */
    protected function setTypes(array $types): void
    {
        $this->types = $types;
    }

    /**
     * Get the generator name.
     */
    public function getName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get the generator description.
     */
    public function getDescription(): string
    {
        return 'Plugin generator provided by ' . $this->plugin->getName();
    }

    /**
     * Check if this generator can handle the given type.
     */
    public function canHandle(string $type): bool
    {
        return in_array($type, $this->types());
    }

    /**
     * Abstract method that must be implemented by concrete generators.
     */
    abstract public function output(Tree $tree): array;
} 
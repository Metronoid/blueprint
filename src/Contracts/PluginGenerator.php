<?php

namespace Blueprint\Contracts;

use Blueprint\Tree;

interface PluginGenerator extends Generator
{
    /**
     * Get the plugin that provides this generator.
     */
    public function getPlugin(): Plugin;

    /**
     * Get the generator priority (higher = runs first).
     */
    public function getPriority(): int;

    /**
     * Check if this generator should run for the given tree.
     */
    public function shouldRun(Tree $tree): bool;

    /**
     * Get the generator configuration.
     */
    public function getConfig(): array;

    /**
     * Set the generator configuration.
     */
    public function setConfig(array $config): void;
} 
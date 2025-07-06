<?php

namespace Blueprint\Contracts;

interface Plugin
{
    /**
     * Get the plugin name.
     */
    public function getName(): string;

    /**
     * Get the plugin version.
     */
    public function getVersion(): string;

    /**
     * Get the plugin description.
     */
    public function getDescription(): string;

    /**
     * Get the plugin author.
     */
    public function getAuthor(): string;

    /**
     * Get the plugin dependencies.
     */
    public function getDependencies(): array;

    /**
     * Get the plugin configuration schema.
     */
    public function getConfigSchema(): array;

    /**
     * Boot the plugin.
     */
    public function boot(): void;

    /**
     * Register the plugin services.
     */
    public function register(): void;

    /**
     * Check if the plugin is compatible with the current Blueprint version.
     */
    public function isCompatible(string $blueprintVersion): bool;

    /**
     * Get the lexers provided by this plugin.
     */
    public function getLexers(): array;
} 
<?php

namespace Blueprint\Contracts;

use Blueprint\Models\Dashboard;

interface DashboardPlugin
{
    /**
     * Get the plugin name
     */
    public function getName(): string;

    /**
     * Get the plugin description
     */
    public function getDescription(): string;

    /**
     * Get the plugin version
     */
    public function getVersion(): string;

    /**
     * Extend the dashboard with plugin-specific widgets
     */
    public function extendDashboard(Dashboard $dashboard): void;

    /**
     * Get plugin-specific widgets
     */
    public function getWidgets(): array;

    /**
     * Get plugin-specific navigation items
     */
    public function getNavigation(): array;

    /**
     * Get plugin-specific permissions
     */
    public function getPermissions(): array;

    /**
     * Get plugin-specific API endpoints
     */
    public function getApiEndpoints(): array;

    /**
     * Get plugin-specific settings
     */
    public function getSettings(): array;

    /**
     * Check if the plugin is enabled
     */
    public function isEnabled(): bool;

    /**
     * Enable the plugin
     */
    public function enable(): void;

    /**
     * Disable the plugin
     */
    public function disable(): void;
} 
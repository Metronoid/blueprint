<?php

namespace Blueprint\Models;

class Dashboard
{
    private string $name;
    private string $title;
    private string $description;
    private array $widgets = [];
    private array $navigation = [];
    private array $settings = [];
    private array $permissions = [];
    private ?string $layout = null;
    private array $theme = [];
    private array $api = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function widgets(): array
    {
        return $this->widgets;
    }

    public function setWidgets(array $widgets): void
    {
        $this->widgets = $widgets;
    }

    public function addWidget(string $name, $widget): void
    {
        if (is_array($widget)) {
            $dashboardWidget = new DashboardWidget($name, $widget['type'] ?? 'metric');
            if (isset($widget['title'])) {
                $dashboardWidget->setTitle($widget['title']);
            }
            if (isset($widget['config'])) {
                $dashboardWidget->setConfig($widget['config']);
            }
            if (isset($widget['data'])) {
                $dashboardWidget->setData($widget['data']);
            }
            if (isset($widget['position'])) {
                $dashboardWidget->setPosition($widget['position']);
            }
            if (isset($widget['permissions'])) {
                $dashboardWidget->setPermissions($widget['permissions']);
            }
            if (isset($widget['model'])) {
                $dashboardWidget->setModel($widget['model']);
            }
            if (isset($widget['columns'])) {
                $dashboardWidget->setColumns($widget['columns']);
            }
            if (isset($widget['filters'])) {
                $dashboardWidget->setFilters($widget['filters']);
            }
            if (isset($widget['actions'])) {
                $dashboardWidget->setActions($widget['actions']);
            }
            $widget = $dashboardWidget;
        }
        $this->widgets[$name] = $widget;
    }

    public function navigation(): array
    {
        return $this->navigation;
    }

    public function setNavigation(array $navigation): void
    {
        $this->navigation = $navigation;
    }

    public function settings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function layout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function theme(): array
    {
        return $this->theme;
    }

    public function setTheme(array $theme): void
    {
        $this->theme = $theme;
    }

    public function api(): array
    {
        return $this->api;
    }

    public function setApi(array $api): void
    {
        $this->api = $api;
    }
} 
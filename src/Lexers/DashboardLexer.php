<?php

namespace Blueprint\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Dashboard;
use Blueprint\Models\DashboardWidget;

class DashboardLexer implements Lexer
{
    public function analyze(array $tokens): array
    {
        $dashboardNames = isset($tokens['dashboards']) ? array_keys($tokens['dashboards']) : [];
        $msg = '[DashboardLexer] Dashboards in tokens: ' . json_encode($dashboardNames);
        file_put_contents('/tmp/dashboardlexer.log', $msg . "\n", FILE_APPEND);
        $registry = [
            'dashboards' => [],
        ];

        if (!isset($tokens['dashboards'])) {
            return $registry;
        }

        foreach ($tokens['dashboards'] as $name => $definition) {
            if (is_array($definition)) {
                $registry['dashboards'][] = $this->analyzeDashboardDefinition($name, $definition);
            } else {
                // Handle null or invalid definitions by creating a basic dashboard
                $registry['dashboards'][] = $this->analyzeDashboardDefinition($name, []);
            }
        }

        return $registry;
    }

    protected function analyzeDashboardDefinition(string $name, array $definition): Dashboard
    {
        $dashboard = new Dashboard($name);

        if (isset($definition['title'])) {
            $dashboard->setTitle($definition['title']);
        }

        if (isset($definition['description'])) {
            $dashboard->setDescription($definition['description']);
        }

        if (isset($definition['layout'])) {
            $dashboard->setLayout($definition['layout']);
        }

        if (isset($definition['theme'])) {
            $dashboard->setTheme($definition['theme']);
        }

        if (isset($definition['permissions'])) {
            if (!is_array($definition['permissions'])) {
                throw new \InvalidArgumentException("Permissions must be an array, got " . gettype($definition['permissions']));
            }
            $dashboard->setPermissions($definition['permissions']);
        }

        if (isset($definition['settings'])) {
            $dashboard->setSettings($definition['settings']);
        }

        if (isset($definition['api'])) {
            $dashboard->setApi($definition['api']);
        }

        if (isset($definition['navigation'])) {
            $dashboard->setNavigation($definition['navigation']);
        }

        if (isset($definition['widgets'])) {
            foreach ($definition['widgets'] as $widgetName => $widgetDefinition) {
                if (is_array($widgetDefinition)) {
                    $widget = $this->analyzeWidgetDefinition($widgetName, $widgetDefinition);
                    $dashboard->addWidget($widgetName, $widget);
                }
            }
        }

        return $dashboard;
    }

    protected function analyzeWidgetDefinition(string $name, array $definition): DashboardWidget
    {
        $type = $definition['type'] ?? 'chart';
        $widget = new DashboardWidget($name, $type);

        if (isset($definition['title'])) {
            $widget->setTitle($definition['title']);
        }

        if (isset($definition['model'])) {
            $widget->setModel($definition['model']);
        }

        if (isset($definition['columns'])) {
            $widget->setColumns($definition['columns']);
        }

        if (isset($definition['data'])) {
            $widget->setData($definition['data']);
        }

        if (isset($definition['config'])) {
            $widget->setConfig($definition['config']);
        }

        if (isset($definition['position'])) {
            $widget->setPosition($definition['position']);
        }

        if (isset($definition['permissions'])) {
            $widget->setPermissions($definition['permissions']);
        }

        if (isset($definition['filters'])) {
            $widget->setFilters($definition['filters']);
        }

        if (isset($definition['actions'])) {
            $widget->setActions($definition['actions']);
        }

        return $widget;
    }
} 
<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ConfigurationGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['config'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        return $this->output;
    }

    protected function generateDashboardConfig($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('config.dashboard.stub');
        if (!$stub) {
            return;
        }

        $path = 'config/dashboard/' . Str::kebab($dashboard->name()) . '.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateDashboardConfigStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetConfig($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('config.widget.stub');
            if (!$stub) {
                continue;
            }

            $path = 'config/widgets/' . Str::kebab($widget->name()) . '.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetConfigStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateDashboardConfigStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ dashboardConfig }}' => $this->generateDashboardConfigArray($dashboard),
            '{{ widgetConfigs }}' => $this->generateWidgetConfigArrays($dashboard, $tree),
            '{{ permissions }}' => $this->generatePermissionsConfig($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetConfigStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ widgetConfig }}' => $this->generateWidgetConfigArray($widget),
            '{{ widgetSettings }}' => $this->generateWidgetSettings($widget),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateDashboardConfigArray($dashboard): string
    {
        $config = [];
        
        // Generate dashboard configuration array
        $config[] = "return [";
        $config[] = "    'name' => '" . Str::studly($dashboard->name()) . "',";
        $config[] = "    'title' => '" . Str::studly($dashboard->name()) . " Dashboard',";
        $config[] = "    'description' => 'Dashboard for " . Str::studly($dashboard->name()) . "',";
        $config[] = "    'layout' => 'grid',";
        $config[] = "    'permissions' => [";
        $config[] = "        'view',";
        $config[] = "        'edit',";
        $config[] = "    ],";
        $config[] = "    'widgets' => [";
        $config[] = "        // Widget configurations will be added here";
        $config[] = "    ],";
        $config[] = "];";

        return implode("\n", $config);
    }

    protected function generateWidgetConfigArrays($dashboard, Tree $tree): string
    {
        $configs = [];
        
        // Generate widget configuration arrays
        foreach ($dashboard->widgets() as $widget) {
            $configs[] = "    '" . Str::kebab($widget->name()) . "' => [";
            $configs[] = "        'name' => '" . Str::studly($widget->name()) . "',";
            $configs[] = "        'type' => '" . $widget->type() . "',";
            $configs[] = "        'enabled' => true,";
            $configs[] = "        'position' => 0,";
            $configs[] = "    ],";
        }

        return implode("\n", $configs);
    }

    protected function generatePermissionsConfig($dashboard): string
    {
        $permissions = [];
        
        // Generate permissions configuration
        $permissions[] = "    'permissions' => [";
        $permissions[] = "        'view' => 'view-" . Str::kebab($dashboard->name()) . "-dashboard',";
        $permissions[] = "        'edit' => 'edit-" . Str::kebab($dashboard->name()) . "-dashboard',";
        $permissions[] = "        'delete' => 'delete-" . Str::kebab($dashboard->name()) . "-dashboard',";
        $permissions[] = "    ],";

        return implode("\n", $permissions);
    }

    protected function generateWidgetConfigArray($widget): string
    {
        $config = [];
        
        // Generate widget configuration array
        $config[] = "return [";
        $config[] = "    'name' => '" . Str::studly($widget->name()) . "',";
        $config[] = "    'type' => '" . $widget->type() . "',";
        $config[] = "    'enabled' => true,";
        $config[] = "    'position' => 0,";
        $config[] = "    'settings' => [";
        $config[] = "        // Widget-specific settings";
        $config[] = "    ],";
        $config[] = "];";

        return implode("\n", $config);
    }

    protected function generateWidgetSettings($widget): string
    {
        $settings = [];
        
        // Generate widget settings
        $settings[] = "    'settings' => [";
        $settings[] = "        'refresh_interval' => 300,";
        $settings[] = "        'max_items' => 10,";
        $settings[] = "        'show_title' => true,";
        $settings[] = "        'show_footer' => false,";
        $settings[] = "    ],";

        return implode("\n", $settings);
    }
} 
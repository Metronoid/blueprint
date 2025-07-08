<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FormRequestGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['requests'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardRequests($dashboard, $tree);
            $this->generateWidgetRequests($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardRequests($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('request.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Http/Requests/Dashboard/' . Str::studly($dashboard->name()) . 'Request.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateRequestStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetRequests($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.request.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Http/Requests/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Request.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetRequestStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateRequestStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Requests\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Request',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ validationRules }}' => $this->generateValidationRules($dashboard),
            '{{ authorizationRules }}' => $this->generateAuthorizationRules($dashboard),
            '{{ customMethods }}' => $this->generateCustomMethods($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetRequestStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Requests\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Request',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ validationRules }}' => $this->generateWidgetValidationRules($widget),
            '{{ authorizationRules }}' => $this->generateWidgetAuthorizationRules($widget),
            '{{ customMethods }}' => $this->generateWidgetCustomMethods($widget),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateValidationRules($dashboard): string
    {
        $rules = [];
        
        // Add dashboard-specific validation rules
        foreach ($dashboard->widgets() as $widget) {
            $widgetRules = $this->generateWidgetValidationRules($widget);
            if (!empty($widgetRules)) {
                $rules[] = "        // Validation rules for {$widget->name()} widget";
                $rules[] = $widgetRules;
            }
        }

        if (empty($rules)) {
            return "        return [];";
        }

        return implode("\n\n", $rules);
    }

    protected function generateAuthorizationRules($dashboard): string
    {
        $permissions = $dashboard->permissions();
        if (empty($permissions)) {
            return "        return true;";
        }

        $checks = [];
        foreach ($permissions as $permission) {
            $checks[] = "        return auth()->user()->can('$permission');";
        }

        return implode("\n", $checks);
    }

    protected function generateCustomMethods($dashboard): string
    {
        $methods = [];
        
        // Add custom methods for dashboard
        $methods[] = "    public function messages()";
        $methods[] = "    {";
        $methods[] = "        return [";
        $methods[] = "            // Custom validation messages";
        $methods[] = "        ];";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateWidgetValidationRules($widget): string
    {
        $rules = [];
        
        // Add widget-specific validation rules
        $config = $widget->config();
        if (!empty($config) && isset($config['validation'])) {
            foreach ($config['validation'] as $field => $rule) {
                $rules[] = "            '$field' => '$rule',";
            }
        }

        if (empty($rules)) {
            return "        return [];";
        }

        return "        return [\n" . implode("\n", $rules) . "\n        ];";
    }

    protected function generateWidgetAuthorizationRules($widget): string
    {
        $permissions = $widget->permissions();
        if (empty($permissions)) {
            return "        return true;";
        }

        $checks = [];
        foreach ($permissions as $permission) {
            $checks[] = "        return auth()->user()->can('$permission');";
        }

        return implode("\n", $checks);
    }

    protected function generateWidgetCustomMethods($widget): string
    {
        $methods = [];
        
        // Add custom methods for widget
        $methods[] = "    public function messages()";
        $methods[] = "    {";
        $methods[] = "        return [";
        $methods[] = "            // Custom validation messages for {$widget->name()} widget";
        $methods[] = "        ];";
        $methods[] = "    }";

        return implode("\n", $methods);
    }
} 
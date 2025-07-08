<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ServiceGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['services'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardService($dashboard, $tree);
            $this->generateWidgetServices($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardService($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('service.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Services/Dashboard/' . Str::studly($dashboard->name()) . 'Service.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateServiceStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetServices($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.service.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Services/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Service.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetServiceStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateServiceStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Service',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ widgetMethods }}' => $this->generateWidgetServiceMethods($dashboard, $tree),
            '{{ modelQueries }}' => $this->generateModelQueries($dashboard, $tree),
            '{{ pluginIntegration }}' => $this->generatePluginIntegrationMethods($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetServiceStub(string $stub, $widget, Tree $tree): string
    {
        $model = $widget->model();
        $modelData = $model ? $tree->modelForContext($model) : null;

        $modelQuery = '';
        $modelImports = '';
        if ($model) {
            $modelQuery = '$query = ' . Str::studly($model) . '::query();';
            $modelImports = 'use App\\Models\\' . Str::studly($model) . ';';
        } else {
            $modelQuery = "// TODO: Specify a model for this widget in your dashboard YAML.\n        throw new \\RuntimeException('No model specified for widget " . Str::studly($widget->name()) . ".');";
        }

        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Service',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ modelName }}' => $model ? Str::studly($model) : '',
            '{{ modelQuery }}' => $modelQuery,
            '{{ modelImports }}' => $modelImports,
            '{{ columns }}' => $this->generateColumnQueries($widget, $modelData),
            '{{ filters }}' => $this->generateFilterQueries($widget),
            '{{ apiIntegration }}' => $this->generateApiIntegration($widget),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetServiceMethods($dashboard, Tree $tree): string
    {
        $methods = [];
        foreach ($dashboard->widgets() as $widget) {
            $model = $widget->model();
            if ($model && $tree->modelForContext($model)) {
                $methods[] = "    public function get" . Str::studly($widget->name()) . "Data()\n    {\n        return " . Str::studly($model) . "::query()\n            ->select(" . $this->generateColumnSelect($widget) . ")\n            ->get();\n    }";
            }
        }
        return implode("\n\n", $methods);
    }

    protected function generateModelQueries($dashboard, Tree $tree): string
    {
        $queries = [];
        foreach ($dashboard->widgets() as $widget) {
            $model = $widget->model();
            if ($model && $tree->modelForContext($model)) {
                $queries[] = "use App\\Models\\" . Str::studly($model) . ";";
            }
        }
        return implode("\n", array_unique($queries));
    }

    protected function generatePluginIntegrationMethods($dashboard): string
    {
        return "    public function getPluginData()\n    {\n        return app(\\App\\Services\\Dashboard\\PluginIntegrationService::class)->getData();\n    }";
    }

    protected function generateColumnSelect($widget): string
    {
        $columns = $widget->columns();
        if (empty($columns)) {
            return "'*'";
        }
        return "'" . implode("', '", $columns) . "'";
    }

    protected function generateColumnQueries($widget, $modelData): string
    {
        if (!$modelData) {
            return '';
        }

        $columns = $widget->columns();
        if (empty($columns)) {
            return "\$query->select('*');";
        }

        $columnQueries = [];
        foreach ($columns as $column) {
            $columnQueries[] = "            ->select('$column')";
        }

        return "\$query\n" . implode("\n", $columnQueries) . ";";
    }

    protected function generateFilterQueries($widget): string
    {
        $filters = $widget->filters();
        if (empty($filters)) {
            return '';
        }

        $filterQueries = [];
        foreach ($filters as $filter) {
            $filterQueries[] = "        if (request('$filter')) {\n            \$query->where('$filter', request('$filter'));\n        }";
        }

        return implode("\n\n", $filterQueries);
    }

    protected function generateApiIntegration($widget): string
    {
        return "    public function getApiData()\n    {\n        // TODO: Implement API integration for " . Str::studly($widget->name()) . "\n        return [];\n    }";
    }
} 
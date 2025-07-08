<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ResourceGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['resources'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardResources($dashboard, $tree);
            $this->generateWidgetResources($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardResources($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('resource.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Http/Resources/Dashboard/' . Str::studly($dashboard->name()) . 'Resource.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateResourceStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetResources($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $model = $widget->model();
            if ($model && $tree->modelForContext($model)) {
                $stub = $this->filesystem->stub('widget.resource.stub');
                if (!$stub) {
                    continue;
                }

                $path = 'app/Http/Resources/' . Str::studly($model) . 'Resource.php';
                
                if ($this->filesystem->exists($path)) {
                    $this->output['skipped'][] = $path;
                    continue;
                }

                $content = $this->populateWidgetResourceStub($stub, $widget, $tree);
                $this->create($path, $content);
            }
        }
    }

    protected function populateResourceStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Resources\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Resource',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ widgetResources }}' => $this->generateWidgetResourceMethods($dashboard, $tree),
            '{{ imports }}' => $this->generateResourceImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetResourceStub(string $stub, $widget, Tree $tree): string
    {
        $model = $widget->model();
        $modelData = $model ? $tree->modelForContext($model) : null;

        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Resources',
            '{{ className }}' => Str::studly($model) . 'Resource',
            '{{ modelName }}' => Str::studly($model),
            '{{ fields }}' => $this->generateResourceFields($widget, $modelData),
            '{{ relationships }}' => $this->generateResourceRelationships($widget, $modelData),
            '{{ imports }}' => $this->generateWidgetResourceImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetResourceMethods($dashboard, Tree $tree): string
    {
        $methods = [];
        foreach ($dashboard->widgets() as $widget) {
            $model = $widget->model();
            if ($model && $tree->modelForContext($model)) {
                $methods[] = "    public function " . Str::camel($widget->name()) . "()\n    {\n        return new " . Str::studly($model) . "Resource(\$this->" . Str::camel($widget->name()) . ");\n    }";
            }
        }
        return implode("\n\n", $methods);
    }

    protected function generateResourceImports($dashboard, Tree $tree): string
    {
        $imports = [];
        foreach ($dashboard->widgets() as $widget) {
            $model = $widget->model();
            if ($model && $tree->modelForContext($model)) {
                $imports[] = "use App\\Http\\Resources\\" . Str::studly($model) . "Resource;";
            }
        }
        return implode("\n", array_unique($imports));
    }

    protected function generateResourceFields($widget, $modelData): string
    {
        if (!$modelData) {
            return "        return [\n            'id' => \$this->id,\n        ];";
        }

        $columns = $widget->columns();
        if (empty($columns)) {
            $columns = array_keys($modelData->columns());
        }

        $fields = [];
        foreach ($columns as $column) {
            $fields[] = "            '$column' => \$this->$column,";
        }

        return "        return [\n" . implode("\n", $fields) . "\n        ];";
    }

    protected function generateResourceRelationships($widget, $modelData): string
    {
        if (!$modelData) {
            return '';
        }

        $relationships = [];
        foreach ($modelData->relationships() as $relationship) {
            $relationships[] = "        return [\n            '$relationship' => new " . Str::studly($relationship) . "Resource(\$this->whenLoaded('$relationship')),\n        ];";
        }

        return implode("\n\n", $relationships);
    }

    protected function generateWidgetResourceImports($widget, Tree $tree): string
    {
        $model = $widget->model();
        if ($model && $tree->modelForContext($model)) {
            return "use App\\Models\\" . Str::studly($model) . ";";
        }
        return '';
    }
} 
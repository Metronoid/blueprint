<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class PluginIntegrationGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['plugins'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        return $this->output;
    }

    protected function generatePluginIntegration($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('plugin-integration.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Services/Dashboard/Plugins/' . Str::studly($dashboard->name()) . 'PluginIntegration.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populatePluginIntegrationStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetPluginIntegration($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.plugin-integration.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Services/Dashboard/Plugins/Widgets/' . Str::studly($widget->name()) . 'PluginIntegration.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetPluginIntegrationStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populatePluginIntegrationStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Dashboard\\Plugins',
            '{{ className }}' => Str::studly($dashboard->name()) . 'PluginIntegration',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ pluginMethods }}' => $this->generatePluginMethods($dashboard),
            '{{ imports }}' => $this->generatePluginImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetPluginIntegrationStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Dashboard\\Plugins\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'PluginIntegration',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ pluginMethods }}' => $this->generateWidgetPluginMethods($widget),
            '{{ imports }}' => $this->generateWidgetPluginImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generatePluginMethods($dashboard): string
    {
        $methods = [];
        
        // Generate plugin methods for dashboard
        $methods[] = "    public function getPluginData()";
        $methods[] = "    {";
        $methods[] = "        // TODO: Implement plugin data retrieval for " . Str::studly($dashboard->name()) . " dashboard";
        $methods[] = "        return [];";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function registerPlugin(\$plugin)";
        $methods[] = "    {";
        $methods[] = "        // TODO: Implement plugin registration for " . Str::studly($dashboard->name()) . " dashboard";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generatePluginImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for plugin integration
        $imports[] = "use Illuminate\\Support\\Collection;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetPluginMethods($widget): string
    {
        $methods = [];
        
        // Generate plugin methods for widget
        $methods[] = "    public function getWidgetPluginData()";
        $methods[] = "    {";
        $methods[] = "        // TODO: Implement plugin data retrieval for " . Str::studly($widget->name()) . " widget";
        $methods[] = "        return [];";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function registerWidgetPlugin(\$plugin)";
        $methods[] = "    {";
        $methods[] = "        // TODO: Implement plugin registration for " . Str::studly($widget->name()) . " widget";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateWidgetPluginImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget plugin integration
        $imports[] = "use Illuminate\\Support\\Collection;";

        return implode("\n", array_unique($imports));
    }
} 
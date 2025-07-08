<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class TypeScriptTypeGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['typescript'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardTypes($dashboard, $tree);
            $this->generateWidgetTypes($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardTypes($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('typescript.types.stub');
        if (!$stub) {
            return;
        }

        $path = 'resources/js/types/dashboard/' . Str::kebab($dashboard->name()) . '.ts';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateDashboardTypesStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetTypes($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('typescript.widget.types.stub');
            if (!$stub) {
                continue;
            }

            $path = 'resources/js/types/widgets/' . Str::kebab($widget->name()) . '.ts';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetTypesStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateDashboardTypesStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ dashboardInterface }}' => $this->generateDashboardInterface($dashboard),
            '{{ widgetTypes }}' => $this->generateWidgetTypeDefinitions($dashboard, $tree),
            '{{ apiTypes }}' => $this->generateApiTypeDefinitions($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetTypesStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ widgetInterface }}' => $this->generateWidgetInterface($widget),
            '{{ widgetProps }}' => $this->generateWidgetProps($widget),
            '{{ widgetData }}' => $this->generateWidgetDataTypes($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateDashboardInterface($dashboard): string
    {
        $interface = [];
        
        // Generate dashboard interface
        $interface[] = "export interface " . Str::studly($dashboard->name()) . "Dashboard {";
        $interface[] = "  id: string;";
        $interface[] = "  name: string;";
        $interface[] = "  widgets: Widget[];";
        $interface[] = "  layout: DashboardLayout;";
        $interface[] = "  permissions: string[];";
        $interface[] = "}";

        return implode("\n", $interface);
    }

    protected function generateWidgetTypeDefinitions($dashboard, Tree $tree): string
    {
        $types = [];
        
        // Generate widget type definitions
        foreach ($dashboard->widgets() as $widget) {
            $types[] = "export interface " . Str::studly($widget->name()) . "Widget {";
            $types[] = "  id: string;";
            $types[] = "  type: '" . $widget->type() . "';";
            $types[] = "  name: string;";
            $types[] = "  data: any;";
            $types[] = "  config: " . Str::studly($widget->name()) . "WidgetConfig;";
            $types[] = "}";
            $types[] = "";
            $types[] = "export interface " . Str::studly($widget->name()) . "WidgetConfig {";
            $types[] = "  // TODO: Define widget configuration interface";
            $types[] = "}";
            $types[] = "";
        }

        return implode("\n", $types);
    }

    protected function generateApiTypeDefinitions($dashboard): string
    {
        $types = [];
        
        // Generate API type definitions
        $types[] = "export interface " . Str::studly($dashboard->name()) . "ApiResponse {";
        $types[] = "  data: " . Str::studly($dashboard->name()) . "Dashboard;";
        $types[] = "  success: boolean;";
        $types[] = "  message?: string;";
        $types[] = "}";

        return implode("\n", $types);
    }

    protected function generateWidgetInterface($widget): string
    {
        $interface = [];
        
        // Generate widget interface
        $interface[] = "export interface " . Str::studly($widget->name()) . "Widget {";
        $interface[] = "  id: string;";
        $interface[] = "  type: '" . $widget->type() . "';";
        $interface[] = "  name: string;";
        $interface[] = "  data: " . Str::studly($widget->name()) . "Data;";
        $interface[] = "  config: " . Str::studly($widget->name()) . "Config;";
        $interface[] = "}";

        return implode("\n", $interface);
    }

    protected function generateWidgetProps($widget): string
    {
        $props = [];
        
        // Generate widget props interface
        $props[] = "export interface " . Str::studly($widget->name()) . "Props {";
        $props[] = "  widget: " . Str::studly($widget->name()) . "Widget;";
        $props[] = "  onUpdate?: (data: any) => void;";
        $props[] = "  onDelete?: () => void;";
        $props[] = "}";

        return implode("\n", $props);
    }

    protected function generateWidgetDataTypes($widget, Tree $tree): string
    {
        $types = [];
        
        // Generate widget data types
        $types[] = "export interface " . Str::studly($widget->name()) . "Data {";
        $types[] = "  // TODO: Define widget data interface";
        $types[] = "}";
        $types[] = "";
        $types[] = "export interface " . Str::studly($widget->name()) . "Config {";
        $types[] = "  // TODO: Define widget configuration interface";
        $types[] = "}";

        return implode("\n", $types);
    }
} 
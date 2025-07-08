<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class EventGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['events'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardEvents($dashboard, $tree);
            $this->generateWidgetEvents($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardEvents($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('event.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Events/Dashboard/' . Str::studly($dashboard->name()) . 'Events.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateEventStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetEvents($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.event.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Events/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Event.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetEventStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateEventStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Events\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Events',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ eventClasses }}' => $this->generateEventClasses($dashboard),
            '{{ imports }}' => $this->generateEventImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetEventStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Events\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Event',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ eventData }}' => $this->generateEventData($widget),
            '{{ imports }}' => $this->generateWidgetEventImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateEventClasses($dashboard): string
    {
        $classes = [];
        
        // Generate event classes for dashboard
        $classes[] = "    // Event classes for " . Str::studly($dashboard->name()) . " dashboard";
        $classes[] = "    public function dashboardCreated()";
        $classes[] = "    {";
        $classes[] = "        // Dashboard created event";
        $classes[] = "    }";
        $classes[] = "";
        $classes[] = "    public function dashboardUpdated()";
        $classes[] = "    {";
        $classes[] = "        // Dashboard updated event";
        $classes[] = "    }";

        return implode("\n", $classes);
    }

    protected function generateEventImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for dashboard events
        $imports[] = "use Illuminate\\Foundation\\Events\\Dispatchable;";
        $imports[] = "use Illuminate\\Queue\\SerializesModels;";

        return implode("\n", array_unique($imports));
    }

    protected function generateEventData($widget): string
    {
        $data = [];
        
        // Generate event data for widget
        $data[] = "    public \$widget;";
        $data[] = "    public \$data;";
        $data[] = "";
        $data[] = "    public function __construct(\$widget, \$data = null)";
        $data[] = "    {";
        $data[] = "        \$this->widget = \$widget;";
        $data[] = "        \$this->data = \$data;";
        $data[] = "    }";

        return implode("\n", $data);
    }

    protected function generateWidgetEventImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget events
        $imports[] = "use Illuminate\\Foundation\\Events\\Dispatchable;";
        $imports[] = "use Illuminate\\Queue\\SerializesModels;";

        return implode("\n", array_unique($imports));
    }
} 
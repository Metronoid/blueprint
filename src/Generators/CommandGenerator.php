<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class CommandGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['commands'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardCommands($dashboard, $tree);
            $this->generateWidgetCommands($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardCommands($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('command.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Console/Commands/Dashboard/' . Str::studly($dashboard->name()) . 'Command.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateCommandStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetCommands($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.command.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Console/Commands/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Command.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetCommandStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateCommandStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Console\\Commands\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Command',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ commandName }}' => 'dashboard:' . Str::kebab($dashboard->name()),
            '{{ commandDescription }}' => 'Manage ' . Str::studly($dashboard->name()) . ' dashboard',
            '{{ commandLogic }}' => $this->generateCommandLogic($dashboard),
            '{{ imports }}' => $this->generateCommandImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetCommandStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Console\\Commands\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Command',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ commandName }}' => 'widget:' . Str::kebab($widget->name()),
            '{{ commandDescription }}' => 'Manage ' . Str::studly($widget->name()) . ' widget',
            '{{ commandLogic }}' => $this->generateWidgetCommandLogic($widget),
            '{{ imports }}' => $this->generateWidgetCommandImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateCommandLogic($dashboard): string
    {
        $logic = [];
        
        // Generate command logic for dashboard
        $logic[] = "    public function handle()";
        $logic[] = "    {";
        $logic[] = "        \$this->info('Managing " . Str::studly($dashboard->name()) . " dashboard...');";
        $logic[] = "";
        $logic[] = "        // TODO: Implement dashboard management logic";
        $logic[] = "";
        $logic[] = "        \$this->info('Dashboard management completed.');";
        $logic[] = "    }";

        return implode("\n", $logic);
    }

    protected function generateCommandImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for dashboard commands
        $imports[] = "use Illuminate\\Console\\Command;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetCommandLogic($widget): string
    {
        $logic = [];
        
        // Generate command logic for widget
        $logic[] = "    public function handle()";
        $logic[] = "    {";
        $logic[] = "        \$this->info('Managing " . Str::studly($widget->name()) . " widget...');";
        $logic[] = "";
        $logic[] = "        // TODO: Implement widget management logic";
        $logic[] = "";
        $logic[] = "        \$this->info('Widget management completed.');";
        $logic[] = "    }";

        return implode("\n", $logic);
    }

    protected function generateWidgetCommandImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget commands
        $imports[] = "use Illuminate\\Console\\Command;";

        return implode("\n", array_unique($imports));
    }
} 
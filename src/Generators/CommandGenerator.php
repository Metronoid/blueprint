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
            '{{ class }}' => Str::studly($dashboard->name()) . 'Command',
            '{{ signature }}' => 'dashboard:' . Str::kebab($dashboard->name()),
            '{{ description }}' => 'Manage ' . Str::studly($dashboard->name()) . ' dashboard',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetCommandStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Console\\Commands\\Dashboard\\Widgets',
            '{{ class }}' => Str::studly($widget->name()) . 'Command',
            '{{ signature }}' => 'widget:' . Str::kebab($widget->name()),
            '{{ description }}' => 'Manage ' . Str::studly($widget->name()) . ' widget',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }


} 
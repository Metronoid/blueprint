<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ServiceProviderGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['providers'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardServiceProvider($dashboard, $tree);
            $this->generateWidgetServiceProvider($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardServiceProvider($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('service-provider.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Providers/Dashboard/' . Str::studly($dashboard->name()) . 'ServiceProvider.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateServiceProviderStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetServiceProvider($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.service-provider.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Providers/Dashboard/Widgets/' . Str::studly($widget->name()) . 'ServiceProvider.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetServiceProviderStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateServiceProviderStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Providers\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'ServiceProvider',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ registerMethod }}' => $this->generateRegisterMethod($dashboard),
            '{{ bootMethod }}' => $this->generateBootMethod($dashboard),
            '{{ imports }}' => $this->generateServiceProviderImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetServiceProviderStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Providers\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'ServiceProvider',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ registerMethod }}' => $this->generateWidgetRegisterMethod($widget),
            '{{ bootMethod }}' => $this->generateWidgetBootMethod($widget),
            '{{ imports }}' => $this->generateWidgetServiceProviderImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateRegisterMethod($dashboard): string
    {
        $method = [];
        
        // Generate register method for dashboard
        $method[] = "    public function register()";
        $method[] = "    {";
        $method[] = "        // Register " . Str::studly($dashboard->name()) . " dashboard services";
        $method[] = "        \$this->app->singleton(";
        $method[] = "            \\App\\Services\\Dashboard\\" . Str::studly($dashboard->name()) . "Service::class,";
        $method[] = "            function (\$app) {";
        $method[] = "                return new \\App\\Services\\Dashboard\\" . Str::studly($dashboard->name()) . "Service();";
        $method[] = "            }";
        $method[] = "        );";
        $method[] = "    }";

        return implode("\n", $method);
    }

    protected function generateBootMethod($dashboard): string
    {
        $method = [];
        
        // Generate boot method for dashboard
        $method[] = "    public function boot()";
        $method[] = "    {";
        $method[] = "        // Boot " . Str::studly($dashboard->name()) . " dashboard";
        $method[] = "        // TODO: Add dashboard-specific boot logic";
        $method[] = "    }";

        return implode("\n", $method);
    }

    protected function generateServiceProviderImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for dashboard service provider
        $imports[] = "use Illuminate\\Support\\ServiceProvider;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetRegisterMethod($widget): string
    {
        $method = [];
        
        // Generate register method for widget
        $method[] = "    public function register()";
        $method[] = "    {";
        $method[] = "        // Register " . Str::studly($widget->name()) . " widget services";
        $method[] = "        \$this->app->singleton(";
        $method[] = "            \\App\\Services\\Dashboard\\Widgets\\" . Str::studly($widget->name()) . "Service::class,";
        $method[] = "            function (\$app) {";
        $method[] = "                return new \\App\\Services\\Dashboard\\Widgets\\" . Str::studly($widget->name()) . "Service();";
        $method[] = "            }";
        $method[] = "        );";
        $method[] = "    }";

        return implode("\n", $method);
    }

    protected function generateWidgetBootMethod($widget): string
    {
        $method = [];
        
        // Generate boot method for widget
        $method[] = "    public function boot()";
        $method[] = "    {";
        $method[] = "        // Boot " . Str::studly($widget->name()) . " widget";
        $method[] = "        // TODO: Add widget-specific boot logic";
        $method[] = "    }";

        return implode("\n", $method);
    }

    protected function generateWidgetServiceProviderImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget service provider
        $imports[] = "use Illuminate\\Support\\ServiceProvider;";

        return implode("\n", array_unique($imports));
    }
} 
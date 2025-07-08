<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MiddlewareGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['middleware'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardMiddleware($dashboard, $tree);
            $this->generateWidgetMiddleware($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardMiddleware($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('middleware.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Http/Middleware/Dashboard/' . Str::studly($dashboard->name()) . 'Middleware.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateMiddlewareStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetMiddleware($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.middleware.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Http/Middleware/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Middleware.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetMiddlewareStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateMiddlewareStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Middleware\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Middleware',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ middlewareLogic }}' => $this->generateMiddlewareLogic($dashboard),
            '{{ imports }}' => $this->generateMiddlewareImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetMiddlewareStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Http\\Middleware\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Middleware',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ middlewareLogic }}' => $this->generateWidgetMiddlewareLogic($widget),
            '{{ imports }}' => $this->generateWidgetMiddlewareImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateMiddlewareLogic($dashboard): string
    {
        $logic = [];
        
        // Generate middleware logic for dashboard
        $logic[] = "    public function handle(\$request, Closure \$next)";
        $logic[] = "    {";
        $logic[] = "        // Check if user has access to " . Str::studly($dashboard->name()) . " dashboard";
        $logic[] = "        if (!auth()->check()) {";
        $logic[] = "            return redirect()->route('login');";
        $logic[] = "        }";
        $logic[] = "";
        $logic[] = "        // TODO: Add dashboard-specific authorization logic";
        $logic[] = "";
        $logic[] = "        return \$next(\$request);";
        $logic[] = "    }";

        return implode("\n", $logic);
    }

    protected function generateMiddlewareImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for dashboard middleware
        $imports[] = "use Closure;";
        $imports[] = "use Illuminate\\Http\\Request;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetMiddlewareLogic($widget): string
    {
        $logic = [];
        
        // Generate middleware logic for widget
        $logic[] = "    public function handle(\$request, Closure \$next)";
        $logic[] = "    {";
        $logic[] = "        // Check if user has access to " . Str::studly($widget->name()) . " widget";
        $logic[] = "        if (!auth()->check()) {";
        $logic[] = "            return redirect()->route('login');";
        $logic[] = "        }";
        $logic[] = "";
        $logic[] = "        // TODO: Add widget-specific authorization logic";
        $logic[] = "";
        $logic[] = "        return \$next(\$request);";
        $logic[] = "    }";

        return implode("\n", $logic);
    }

    protected function generateWidgetMiddlewareImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget middleware
        $imports[] = "use Closure;";
        $imports[] = "use Illuminate\\Http\\Request;";

        return implode("\n", array_unique($imports));
    }
} 
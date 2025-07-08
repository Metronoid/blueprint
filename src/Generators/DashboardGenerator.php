<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class DashboardGenerator implements Generator
{
    protected array $types = ['dashboard'];

    protected array $output = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function output(Tree $tree, $overwriteMigrations = false): array
    {
        $dashboardNames = array_map(fn($d) => $d->name(), $tree->dashboards());
        $msg = '[DashboardGenerator] Dashboards in tree: ' . json_encode($dashboardNames);
        if (function_exists('info')) {
            info($msg);
        } else if (function_exists('error_log')) {
            error_log($msg);
        }
        file_put_contents('/tmp/dashboardgen.log', $msg . "\n", FILE_APPEND);
        $this->output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboard($dashboard, $tree, $overwriteMigrations);
        }

        // Ensure output is always top-level keys
        return [
            'created' => $this->output['created'],
            'updated' => $this->output['updated'],
            'skipped' => $this->output['skipped'],
        ];
    }

    protected function generateDashboard($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        // Generate backend components
        $this->generateBackendComponents($dashboard, $tree, $overwriteMigrations);
        
        // Generate frontend components
        $this->generateFrontendComponents($dashboard, $tree, $overwriteMigrations);
        
        // Generate API routes
        $this->generateApiRoutes($dashboard, $overwriteMigrations);
        
        // Generate dashboard configuration
        $this->generateDashboardConfig($dashboard, $overwriteMigrations);
    }

    protected function generateBackendComponents($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        // Generate Dashboard Controller
        $this->generateDashboardController($dashboard, $overwriteMigrations);
        // Register middleware alias for Laravel 12+
        $this->registerDashboardAccessMiddlewareAlias();
        // Generate Dashboard Service
        $this->generateDashboardService($dashboard, $tree, $overwriteMigrations);
        // Generate Widget Services
        $this->generateWidgetServices($dashboard, $tree, $overwriteMigrations);
        // Generate Dashboard Middleware
        $this->generateDashboardMiddleware($dashboard, $overwriteMigrations);
        // Generate Dashboard Policies
        $this->generateDashboardPolicies($dashboard, $overwriteMigrations);
    }

    protected function generateFrontendComponents($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        // Generate Dashboard Layout
        $this->generateDashboardLayout($dashboard, $overwriteMigrations);
        
        // Generate Dashboard Page
        $this->generateDashboardPage($dashboard, $overwriteMigrations);
        
        // Generate Widget Components
        $this->generateWidgetComponents($dashboard, $overwriteMigrations);
        
        // Generate Dashboard Store (if using state management)
        $this->generateDashboardStore($dashboard, $overwriteMigrations);
        
        // Generate Dashboard Styles
        $this->generateDashboardStyles($dashboard, $overwriteMigrations);
    }

    protected function generateDashboardController($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.controller.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Http/Controllers/Dashboard/' . Str::studly($dashboard->name()) . 'Controller.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateControllerStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardService($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.service.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Services/Dashboard/' . Str::studly($dashboard->name()) . 'Service.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateServiceStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetServices($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('dashboard.widget.service.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Services/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Service.php';
            
            if ($this->filesystem->exists($path) && !$overwriteMigrations) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetServiceStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function generateDashboardMiddleware($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.middleware.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Http/Middleware/DashboardAccess.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateMiddlewareStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardPolicies($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.policy.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Policies/Dashboard/' . Str::studly($dashboard->name()) . 'Policy.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populatePolicyStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardLayout($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.layout.stub');
        if (!$stub) {
            return;
        }

        $path = 'resources/js/Layouts/DashboardLayout.jsx';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateLayoutStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardPage($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.page.stub');
        if (!$stub) {
            return;
        }

        $path = 'resources/js/Pages/Dashboard/' . Str::studly($dashboard->name()) . '.jsx';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populatePageStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateWidgetComponents($dashboard, $overwriteMigrations = false): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('dashboard.widget.component.stub');
            if (!$stub) {
                continue;
            }

            $path = 'resources/js/Components/Dashboard/Widgets/' . Str::studly($widget->name()) . '.jsx';
            
            if ($this->filesystem->exists($path) && !$overwriteMigrations) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetComponentStub($stub, $widget);
            $this->create($path, $content);
        }
    }

    protected function generateDashboardStore($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.store.stub');
        if (!$stub) {
            return;
        }

        $path = 'resources/js/Stores/DashboardStore.js';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateStoreStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardStyles($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.styles.stub');
        if (!$stub) {
            return;
        }

        $path = 'resources/css/dashboard.css';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateStylesStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateApiRoutes($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.routes.stub');
        if (!$stub) {
            return;
        }

        $path = 'routes/dashboard.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateRoutesStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function generateDashboardConfig($dashboard, $overwriteMigrations = false): void
    {
        $stub = $this->filesystem->stub('dashboard.config.stub');
        if (!$stub) {
            return;
        }

        $path = 'config/dashboard.php';
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateConfigStub($stub, $dashboard);
        $this->create($path, $content);
    }

    protected function populateControllerStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ dashboardTitle }}' => $dashboard->title(),
            '{{ dashboardDescription }}' => $dashboard->description(),
            '{{ widgets }}' => $this->generateWidgetArrayEntries($dashboard),
            '{{ widgetMethods }}' => $this->generateWidgetMethods($dashboard),
            '{{ widgetImports }}' => $this->generateWidgetImports($dashboard),
            '{{ permissions }}' => $this->generatePermissionChecks($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetArrayEntries($dashboard): string
    {
        $entries = [];
        foreach ($dashboard->widgets() as $widget) {
            $entries[] = str_repeat(' ', 12) . "'" . $widget->name() . "' => \$this->get" . Str::studly($widget->name()) . "Data(),";
        }
        return implode("\n", $entries);
    }

    protected function populateServiceStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ widgetMethods }}' => $this->generateWidgetServiceMethods($dashboard, $tree),
            '{{ modelQueries }}' => $this->generateModelQueries($dashboard, $tree),
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
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ modelName }}' => $model ? Str::studly($model) : '',
            '{{ modelQuery }}' => $modelQuery,
            '{{ modelImports }}' => $modelImports,
            '{{ columns }}' => $this->generateColumnQueries($widget, $modelData),
            '{{ filters }}' => $this->generateFilterQueries($widget),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateLayoutStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ navigation }}' => $this->generateNavigationJSX($dashboard),
            '{{ theme }}' => $this->generateThemeConfig($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populatePageStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ dashboardTitle }}' => $dashboard->title(),
            '{{ widgets }}' => $this->generateWidgetJSX($dashboard),
            '{{ api }}' => $this->generateApiCalls($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetComponentStub(string $stub, $widget): string
    {
        $replacements = [
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ widgetTitle }}' => $widget->title(),
            '{{ config }}' => $this->generateWidgetConfig($widget),
            '{{ actions }}' => $this->generateWidgetActions($widget),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetMethods($dashboard): string
    {
        $methods = [];
        foreach ($dashboard->widgets() as $widget) {
            $methods[] = "    public function get" . Str::studly($widget->name()) . "Data()\n    {\n        return app(\\App\\Services\\Dashboard\\Widgets\\" . Str::studly($widget->name()) . "Service::class)->getData();\n    }";
        }
        return implode("\n\n", $methods);
    }

    protected function generateWidgetImports($dashboard): string
    {
        $imports = [];
        foreach ($dashboard->widgets() as $widget) {
            $imports[] = "use App\\Services\\Dashboard\\Widgets\\" . Str::studly($widget->name()) . "Service;";
        }
        return implode("\n", $imports);
    }

    protected function generatePermissionChecks($dashboard): string
    {
        $checks = [];
        foreach ($dashboard->permissions() as $permission) {
            $checks[] = "        Gate::authorize('" . $permission . "');";
        }
        return implode("\n", $checks);
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

    protected function generateColumnSelect($widget): string
    {
        $columns = $widget->columns();
        if (empty($columns)) {
            return "'*'";
        }
        return "'" . implode("', '", $columns) . "'";
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

    protected function generateNavigationJSX($dashboard): string
    {
        $navigation = $dashboard->navigation();
        if (empty($navigation)) {
            return '';
        }

        $navItems = [];
        foreach ($navigation as $item) {
            $navItems[] = "        <li key=\"" . $item['name'] . "\">\n            <Link href=\"" . $item['route'] . "\">" . $item['title'] . "</Link>\n        </li>";
        }

        return implode("\n", $navItems);
    }

    protected function generateThemeConfig($dashboard): string
    {
        $theme = $dashboard->theme();
        if (empty($theme)) {
            return '{}';
        }

        return json_encode($theme, JSON_PRETTY_PRINT);
    }

    protected function generateWidgetJSX($dashboard): string
    {
        $widgets = [];
        foreach ($dashboard->widgets() as $widget) {
            $position = $widget->position();
            $style = !empty($position) ? " style={{ gridArea: '" . $position['area'] . "' }}" : '';
            
            $widgets[] = "        <" . Str::studly($widget->name()) . "Widget\n            key=\"" . $widget->name() . "\"\n            data={widgetData." . $widget->name() . "}\n            config={widgetConfig." . $widget->name() . "}\n            $style\n        />";
        }

        return implode("\n", $widgets);
    }

    protected function generateApiCalls($dashboard): string
    {
        $api = $dashboard->api();
        if (empty($api)) {
            return '';
        }

        $apiCalls = [];
        foreach ($api as $endpoint => $config) {
            $apiCalls[] = "    const fetch" . Str::studly($endpoint) . " = async () => {\n        const response = await fetch('" . $config['url'] . "');\n        return response.json();\n    };";
        }

        return implode("\n\n", $apiCalls);
    }

    protected function generateWidgetConfig($widget): string
    {
        $config = $widget->config();
        if (empty($config)) {
            return '{}';
        }

        return json_encode($config, JSON_PRETTY_PRINT);
    }

    protected function generateWidgetActions($widget): string
    {
        $actions = $widget->actions();
        if (empty($actions)) {
            return '';
        }

        $actionMethods = [];
        foreach ($actions as $action) {
            $actionMethods[] = "    const handle" . Str::studly($action['name']) . " = () => {\n        // Handle " . $action['name'] . " action\n    };";
        }

        return implode("\n\n", $actionMethods);
    }

    protected function populateMiddlewareStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ permissions }}' => $this->generatePermissionChecks($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populatePolicyStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ permissions }}' => $this->generatePolicyMethods($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generatePolicyMethods($dashboard): string
    {
        $permissions = $dashboard->permissions();
        if (empty($permissions)) {
            return '';
        }

        $methods = [];
        foreach ($permissions as $permission) {
            $methods[] = "    public function " . Str::camel($permission) . "(User \$user): bool\n    {\n        return \$user->hasPermissionTo('" . $permission . "');\n    }";
        }

        return implode("\n\n", $methods);
    }

    protected function populateStoreStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ widgetState }}' => $this->generateWidgetState($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetState($dashboard): string
    {
        $widgets = [];
        foreach ($dashboard->widgets() as $widget) {
            $widgets[] = "    " . $widget->name() . ": {\n        data: null,\n        loading: false,\n        error: null\n    }";
        }

        return implode(",\n", $widgets);
    }

    protected function populateStylesStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::kebab($dashboard->name()),
            '{{ theme }}' => $this->generateThemeCSS($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateThemeCSS($dashboard): string
    {
        $theme = $dashboard->theme();
        if (empty($theme)) {
            return '';
        }

        $css = [];
        foreach ($theme as $property => $value) {
            $css[] = "    $property: $value;";
        }

        return implode("\n", $css);
    }

    protected function populateRoutesStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ widgetRoutes }}' => $this->generateWidgetRoutes($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateWidgetRoutes($dashboard): string
    {
        $routes = [];
        foreach ($dashboard->widgets() as $widget) {
            $routes[] = "Route::get('/widgets/" . $widget->name() . "/data', [" . Str::studly($dashboard->name()) . "Controller::class, 'get" . Str::studly($widget->name()) . "Data']);";
        }

        return implode("\n", $routes);
    }

    protected function populateConfigStub(string $stub, $dashboard): string
    {
        $replacements = [
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ dashboardConfig }}' => $this->generateDashboardConfigArray($dashboard),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateDashboardConfigArray($dashboard): string
    {
        $config = [
            'title' => $dashboard->title(),
            'description' => $dashboard->description(),
            'layout' => $dashboard->layout(),
            'theme' => $dashboard->theme(),
            'permissions' => $dashboard->permissions(),
            'widgets' => []
        ];

        foreach ($dashboard->widgets() as $widget) {
            $config['widgets'][$widget->name()] = [
                'type' => $widget->type(),
                'title' => $widget->title(),
                'position' => $widget->position(),
                'permissions' => $widget->permissions(),
            ];
        }

        return var_export($config, true);
    }

    protected function create(string $path, string $content): void
    {
        $this->filesystem->makeDirectory(dirname($path), 0755, true, true);
        $this->filesystem->put($path, $content);
        $this->output['created'][] = $path;
        if (function_exists('info')) {
            info('[DashboardGenerator] Created: ' . $path);
        } else {
            error_log('[DashboardGenerator] Created: ' . $path);
        }
    }

    protected function registerDashboardAccessMiddlewareAlias(): void
    {
        $bootstrapAppPath = 'bootstrap/app.php';
        if (!$this->filesystem->exists($bootstrapAppPath)) {
            return;
        }
        $content = $this->filesystem->get($bootstrapAppPath);
        $aliasLine = "        \$middleware->alias(['dashboard.access' => \\App\\Http\\Middleware\\DashboardAccess::class]);";
        if (strpos($content, "dashboard.access") === false) {
            $content = preg_replace(
                '/(->withMiddleware\(function \(Middleware \\$middleware\) \{\n)/',
                "$1$aliasLine\n",
                $content
            );
            $this->filesystem->put($bootstrapAppPath, $content);
        }
    }

    public function types(): array
    {
        return $this->types;
    }
} 
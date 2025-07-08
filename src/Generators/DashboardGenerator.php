<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Blueprint\Generators\ControllerGenerator;
use Blueprint\Generators\PolicyGenerator;
use Blueprint\Generators\RouteGenerator;
use Blueprint\Generators\SeederGenerator;
use Blueprint\Generators\FrontendGenerator;
use Blueprint\Generators\ServiceGenerator;
use Blueprint\Generators\ResourceGenerator;
use Blueprint\Generators\FormRequestGenerator;
use Blueprint\Generators\EventGenerator;
use Blueprint\Generators\CommandGenerator;
use Blueprint\Generators\MiddlewareGenerator;
use Blueprint\Generators\ServiceProviderGenerator;
use Blueprint\Generators\TestGenerator;
use Blueprint\Generators\PluginIntegrationGenerator;
use Blueprint\Generators\TypeScriptTypeGenerator;
use Blueprint\Generators\ConfigurationGenerator;
use Blueprint\Generators\MonitoringGenerator;

class DashboardGenerator implements Generator
{
    protected array $types = ['dashboard'];

    protected array $output = [];

    protected Filesystem $filesystem;

    public function types(): array
    {
        return $this->types;
    }

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function output(Tree $tree, $overwriteMigrations = false): array
    {
        $this->output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboard($dashboard, $tree, $overwriteMigrations);
        }

        return [
            'created' => $this->output['created'],
            'updated' => $this->output['updated'],
            'skipped' => $this->output['skipped'],
        ];
    }

    protected function generateDashboard($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        // Generate dashboard-specific files
        $this->generateDashboardController($dashboard, $tree);
        $this->generateDashboardService($dashboard, $tree);
        $this->generateDashboardFrontend($dashboard, $tree);
        
        // Delegate to existing generators for other components
        $this->delegateToExistingGenerators($tree, $overwriteMigrations);
        
        // Delegate to new specialized generators
        $this->delegateToSpecializedGenerators($dashboard, $tree, $overwriteMigrations);
    }

    protected function generateDashboardController($dashboard, Tree $tree): void
    {
        $controllerName = $dashboard->name() . 'Controller';
        $controllerPath = 'app/Http/Controllers/' . $controllerName . '.php';
        
        $stub = $this->filesystem->get($this->getStubPath('dashboard.controller.stub'));
        $stub = str_replace('{{ class }}', $controllerName, $stub);
        $stub = str_replace('{{ dashboard }}', $dashboard->name(), $stub);
        $stub = str_replace('{{ title }}', $dashboard->title(), $stub);
        
        $this->filesystem->put($controllerPath, $stub);
        $this->output['created'][] = $controllerPath;
    }

    protected function generateDashboardService($dashboard, Tree $tree): void
    {
        $serviceName = $dashboard->name() . 'Service';
        $servicePath = 'app/Services/' . $serviceName . '.php';
        
        $stub = $this->filesystem->get($this->getStubPath('dashboard.service.stub'));
        $stub = str_replace('{{ class }}', $serviceName, $stub);
        $stub = str_replace('{{ dashboard }}', $dashboard->name(), $stub);
        
        $this->filesystem->put($servicePath, $stub);
        $this->output['created'][] = $servicePath;
    }

    protected function generateDashboardFrontend($dashboard, Tree $tree): void
    {
        $componentName = Str::kebab($dashboard->name());
        $componentPath = 'resources/js/Pages/Dashboard/' . $dashboard->name() . '.jsx';
        
        $stub = $this->filesystem->get($this->getStubPath('dashboard.page.stub'));
        $stub = str_replace('{{ component }}', $dashboard->name(), $stub);
        $stub = str_replace('{{ title }}', $dashboard->title(), $stub);
        
        $this->filesystem->put($componentPath, $stub);
        $this->output['created'][] = $componentPath;
        
        // Also generate the layout component
        $layoutPath = 'resources/js/Components/Dashboard/' . $dashboard->name() . 'Layout.jsx';
        $layoutStub = $this->filesystem->get($this->getStubPath('dashboard.layout.stub'));
        if ($layoutStub) {
            $layoutStub = str_replace('{{ component }}', $dashboard->name(), $layoutStub);
            $layoutStub = str_replace('{{ title }}', $dashboard->title(), $layoutStub);
            $this->filesystem->put($layoutPath, $layoutStub);
            $this->output['created'][] = $layoutPath;
        }
    }

    protected function getStubPath(string $stub): string
    {
        $customStubPath = base_path('stubs/blueprint/' . $stub);
        $defaultStubPath = dirname(__DIR__) . '/../stubs/' . $stub;
        
        if ($this->filesystem->exists($customStubPath)) {
            return $customStubPath;
        }
        
        if ($this->filesystem->exists($defaultStubPath)) {
            return $defaultStubPath;
        }
        
        // If stub doesn't exist, create a basic one
        return $this->createBasicStub($stub);
    }

    protected function createBasicStub(string $stub): string
    {
        $stubPath = dirname(__DIR__) . '/../stubs/' . $stub;
        
        switch ($stub) {
            case 'dashboard.controller.stub':
                $content = $this->getBasicControllerStub();
                break;
            case 'dashboard.service.stub':
                $content = $this->getBasicServiceStub();
                break;
            case 'dashboard.page.stub':
                $content = $this->getBasicPageStub();
                break;
            default:
                $content = "// Basic stub for $stub\n";
        }
        
        $this->filesystem->makeDirectory(dirname($stubPath), 0755, true, true);
        $this->filesystem->put($stubPath, $content);
        
        return $stubPath;
    }

    protected function getBasicControllerStub(): string
    {
        return '<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\{{ class }};

class {{ class }} extends Controller
{
    protected {{ class }} $dashboardService;

    public function __construct({{ class }} $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = $this->dashboardService->getDashboardData();
        
        if (request()->expectsJson()) {
            return response()->json($dashboardData);
        }
        
        return view("dashboard.{{ dashboard }}", [
            "dashboard" => $dashboardData["dashboard"],
            "widgets" => $dashboardData["widgets"]
        ]);
    }

    public function widgetData(Request $request, string $widget)
    {
        $widgetData = $this->dashboardService->getWidgetData($widget);
        
        if (!$widgetData) {
            return response()->json(["error" => "Widget not found"], 404);
        }
        
        return response()->json($widgetData);
    }
}';
    }

    protected function getBasicServiceStub(): string
    {
        return '<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class {{ class }}
{
    protected array $dashboardConfig;

    public function __construct()
    {
        $this->dashboardConfig = $this->loadDashboardConfig();
    }

    public function getDashboardData(): array
    {
        return [
            "dashboard" => $this->dashboardConfig,
            "widgets" => $this->getWidgetData()
        ];
    }

    public function getWidgetData(?string $widgetName = null): array
    {
        $widgetData = [];
        
        foreach ($this->dashboardConfig["widgets"] ?? [] as $name => $config) {
            if ($widgetName && $name !== $widgetName) {
                continue;
            }
            
            $widgetData[$name] = $this->generateWidgetData($name, $config);
        }
        
        return $widgetData;
    }

    protected function loadDashboardConfig(): array
    {
        return [
            "title" => "{{ dashboard }} Dashboard",
            "description" => "Dashboard for {{ dashboard }}",
            "layout" => "admin",
            "theme" => [
                "primary_color" => "#1f2937",
                "secondary_color" => "#6b7280",
                "accent_color" => "#3b82f6"
            ],
            "permissions" => ["view-dashboard"],
            "navigation" => [
                [
                    "name" => "overview",
                    "title" => "Overview",
                    "route" => "/dashboard",
                    "icon" => "home"
                ]
            ],
            "widgets" => [
                "Stats" => [
                    "type" => "metric",
                    "title" => "Statistics",
                    "config" => [
                        "format" => "number",
                        "color" => "blue"
                    ]
                ]
            ]
        ];
    }

    protected function generateWidgetData(string $widgetName, array $widgetConfig): array
    {
        switch ($widgetConfig["type"]) {
            case "metric":
                return [
                    "data" => ["value" => 100],
                    "config" => $widgetConfig
                ];
            case "table":
                return [
                    "data" => [
                        "headers" => ["ID", "Name"],
                        "rows" => [["1", "Test"]]
                    ],
                    "config" => $widgetConfig
                ];
            default:
                return [
                    "data" => [],
                    "config" => $widgetConfig
                ];
        }
    }
}';
    }

    protected function getBasicPageStub(): string
    {
        return 'import React, { useState, useEffect } from "react";
import { Head } from "@inertiajs/react";

interface {{ component }}Props {
    dashboard: {
        title: string;
        description: string;
        layout: string;
        theme: {
            primary_color: string;
            secondary_color: string;
            accent_color: string;
        };
        navigation: Array<{
            name: string;
            title: string;
            route: string;
            icon: string;
        }>;
        widgets: Record<string, any>;
    };
    widgets: Record<string, any>;
}

export default function {{ component }}({ dashboard, widgets }: {{ component }}Props) {
    const [loading, setLoading] = useState(false);
    const [widgetData, setWidgetData] = useState(widgets);

    useEffect(() => {
        console.log("Dashboard loaded:", dashboard.title);
    }, [dashboard]);

    const refreshWidget = async (widgetName: string) => {
        setLoading(true);
        try {
            const response = await fetch(`/blueprint/dashboard/widgets/${widgetName}/data`);
            if (response.ok) {
                const data = await response.json();
                setWidgetData(prev => ({
                    ...prev,
                    [widgetName]: data
                }));
            }
        } catch (error) {
            console.error("Error refreshing widget:", error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title={dashboard.title} />
            
            <div className="min-h-screen bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-gray-900">
                            {dashboard.title}
                        </h1>
                        <p className="mt-2 text-gray-600">
                            {dashboard.description}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {Object.entries(dashboard.widgets).map(([widgetName, widgetConfig]) => (
                            <div
                                key={widgetName}
                                className="bg-white rounded-lg shadow p-6"
                                style={{
                                    borderLeft: `4px solid ${dashboard.theme.accent_color}`
                                }}
                            >
                                <div className="flex items-center justify-between mb-4">
                                    <h3 className="text-lg font-semibold text-gray-900">
                                        {widgetConfig.title}
                                    </h3>
                                    <button
                                        onClick={() => refreshWidget(widgetName)}
                                        disabled={loading}
                                        className="text-gray-400 hover:text-gray-600 disabled:opacity-50"
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <div className="widget-content">
                                    {widgetData[widgetName] && (
                                        <div className="text-2xl font-bold text-gray-900">
                                            {widgetData[widgetName].data?.value || "N/A"}
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}';
    }

    protected function delegateToExistingGenerators(Tree $tree, $overwriteMigrations = false): void
    {
        // Delegate to ControllerGenerator
        $controllerGenerator = new ControllerGenerator($this->filesystem);
        $controllerOutput = $controllerGenerator->output($tree);
        $this->mergeOutput($controllerOutput);

        // Delegate to PolicyGenerator
        $policyGenerator = new PolicyGenerator($this->filesystem);
        $policyOutput = $policyGenerator->output($tree);
        $this->mergeOutput($policyOutput);

        // Delegate to RouteGenerator
        $routeGenerator = new RouteGenerator($this->filesystem);
        $routeOutput = $routeGenerator->output($tree);
        $this->mergeOutput($routeOutput);

        // Delegate to SeederGenerator
        $seederGenerator = new SeederGenerator($this->filesystem);
        $seederOutput = $seederGenerator->output($tree);
        $this->mergeOutput($seederOutput);

        // Delegate to FrontendGenerator
        $frontendGenerator = new FrontendGenerator($this->filesystem);
        $frontendOutput = $frontendGenerator->output($tree);
        $this->mergeOutput($frontendOutput);
    }

    protected function delegateToSpecializedGenerators($dashboard, Tree $tree, $overwriteMigrations = false): void
    {
        // Delegate to ServiceGenerator
        $serviceGenerator = new ServiceGenerator($this->filesystem);
        $serviceOutput = $serviceGenerator->output($tree);
        $this->mergeOutput($serviceOutput);

        // Delegate to ResourceGenerator
        $resourceGenerator = new ResourceGenerator($this->filesystem);
        $resourceOutput = $resourceGenerator->output($tree);
        $this->mergeOutput($resourceOutput);

        // Delegate to FormRequestGenerator
        $formRequestGenerator = new FormRequestGenerator($this->filesystem);
        $formRequestOutput = $formRequestGenerator->output($tree);
        $this->mergeOutput($formRequestOutput);

        // Delegate to EventGenerator
        $eventGenerator = new EventGenerator($this->filesystem);
        $eventOutput = $eventGenerator->output($tree);
        $this->mergeOutput($eventOutput);

        // Delegate to CommandGenerator
        $commandGenerator = new CommandGenerator($this->filesystem);
        $commandOutput = $commandGenerator->output($tree);
        $this->mergeOutput($commandOutput);

        // Delegate to MiddlewareGenerator
        $middlewareGenerator = new MiddlewareGenerator($this->filesystem);
        $middlewareOutput = $middlewareGenerator->output($tree);
        $this->mergeOutput($middlewareOutput);

        // Delegate to ServiceProviderGenerator
        $serviceProviderGenerator = new ServiceProviderGenerator($this->filesystem);
        $serviceProviderOutput = $serviceProviderGenerator->output($tree);
        $this->mergeOutput($serviceProviderOutput);

        // Delegate to TestGenerator
        $testGenerator = new TestGenerator($this->filesystem);
        $testOutput = $testGenerator->output($tree);
        $this->mergeOutput($testOutput);

        // Delegate to PluginIntegrationGenerator
        $pluginIntegrationGenerator = new PluginIntegrationGenerator($this->filesystem);
        $pluginOutput = $pluginIntegrationGenerator->output($tree);
        $this->mergeOutput($pluginOutput);

        // Delegate to TypeScriptTypeGenerator
        $typeScriptGenerator = new TypeScriptTypeGenerator($this->filesystem);
        $typeScriptOutput = $typeScriptGenerator->output($tree);
        $this->mergeOutput($typeScriptOutput);

        // Delegate to ConfigurationGenerator
        $configGenerator = new ConfigurationGenerator($this->filesystem);
        $configOutput = $configGenerator->output($tree);
        $this->mergeOutput($configOutput);

        // Delegate to MonitoringGenerator
        $monitoringGenerator = new MonitoringGenerator($this->filesystem);
        $monitoringOutput = $monitoringGenerator->output($tree);
        $this->mergeOutput($monitoringOutput);
    }

    protected function mergeOutput(array $otherOutput): void
    {
        foreach (['created', 'updated', 'skipped'] as $key) {
            if (isset($otherOutput[$key])) {
                $this->output[$key] = array_merge($this->output[$key], $otherOutput[$key]);
            }
        }
    }
} 
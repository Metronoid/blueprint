<?php

namespace Blueprint\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Blueprint\Blueprint;
use Blueprint\Tree;
use Blueprint\Services\DashboardPluginManager;
use Symfony\Component\Yaml\Yaml;

class DashboardController extends Controller
{
    public function index()
    {
        // Load the default Blueprint dashboard configuration
        $dashboardConfig = $this->loadBlueprintDashboard();
        
        if (request()->expectsJson()) {
            return $this->getDashboardData($dashboardConfig);
        }
        
        return view('dashboard.index', [
            'dashboard' => $dashboardConfig,
            'widgets' => $this->getWidgetData($dashboardConfig)
        ]);
    }

    public function widgetData(Request $request, string $widget)
    {
        $dashboardConfig = $this->loadBlueprintDashboard();
        $widgetConfig = $this->findWidget($dashboardConfig, $widget);
        
        if (!$widgetConfig) {
            return response()->json(['error' => 'Widget not found'], 404);
        }

        return response()->json($this->getWidgetData($dashboardConfig)[$widget] ?? []);
    }

    protected function loadBlueprintDashboard(): array
    {
        $baseDashboardPath = dirname(__DIR__) . '/../stubs/dashboard.base.yaml';
        
        if (!file_exists($baseDashboardPath)) {
            return $this->getDefaultDashboardConfig();
        }

        $content = file_get_contents($baseDashboardPath);
        
        try {
            $config = Yaml::parse($content);
            $dashboardConfig = $config['dashboards']['AdminDashboard'] ?? $this->getDefaultDashboardConfig();
        } catch (\Exception $e) {
            // If YAML parsing fails, use default config
            $dashboardConfig = $this->getDefaultDashboardConfig();
        }
        
        // Extend with plugin data
        $this->extendWithPlugins($dashboardConfig);
        
        return $dashboardConfig;
    }

    protected function extendWithPlugins(array &$dashboardConfig): void
    {
        $pluginManager = app(DashboardPluginManager::class);
        
        // Add plugin widgets
        $pluginWidgets = $pluginManager->getPluginWidgets();
        $dashboardConfig['widgets'] = array_merge($dashboardConfig['widgets'] ?? [], $pluginWidgets);
        
        // Add plugin navigation
        $pluginNavigation = $pluginManager->getPluginNavigation();
        $dashboardConfig['navigation'] = array_merge($dashboardConfig['navigation'] ?? [], $pluginNavigation);
        
        // Add plugin permissions
        $pluginPermissions = $pluginManager->getPluginPermissions();
        $dashboardConfig['permissions'] = array_merge($dashboardConfig['permissions'] ?? [], $pluginPermissions);
        
        // Add plugin API endpoints
        $pluginApiEndpoints = $pluginManager->getPluginApiEndpoints();
        $dashboardConfig['api'] = array_merge($dashboardConfig['api'] ?? [], $pluginApiEndpoints);
        
        // Add plugin settings
        $pluginSettings = $pluginManager->getPluginSettings();
        $dashboardConfig['settings'] = array_merge($dashboardConfig['settings'] ?? [], $pluginSettings);
    }

    protected function getDefaultDashboardConfig(): array
    {
        return [
            'title' => 'Blueprint Dashboard',
            'description' => 'Overview and extension area for all Blueprint plugins',
            'layout' => 'admin',
            'theme' => [
                'primary_color' => '#1f2937',
                'secondary_color' => '#6b7280',
                'accent_color' => '#3b82f6'
            ],
            'permissions' => ['view-dashboard'],
            'navigation' => [
                [
                    'name' => 'overview',
                    'title' => 'Overview',
                    'route' => '/blueprint/dashboard',
                    'icon' => 'home'
                ],
                [
                    'name' => 'plugins',
                    'title' => 'Plugins',
                    'route' => '/blueprint/plugins',
                    'icon' => 'puzzle'
                ],
                [
                    'name' => 'generators',
                    'title' => 'Generators',
                    'route' => '/blueprint/generators',
                    'icon' => 'code'
                ]
            ],
            'widgets' => [
                'BlueprintStats' => [
                    'type' => 'metric',
                    'title' => 'Blueprint Status',
                    'config' => [
                        'format' => 'status',
                        'color' => 'green'
                    ]
                ],
                'PluginOverview' => [
                    'type' => 'list',
                    'title' => 'Active Plugins',
                    'config' => [
                        'limit' => 10
                    ]
                ],
                'RecentGenerations' => [
                    'type' => 'table',
                    'title' => 'Recent Generations',
                    'config' => [
                        'limit' => 5,
                        'sort_by' => 'created_at',
                        'sort_order' => 'desc'
                    ]
                ]
            ]
        ];
    }

    protected function getDashboardData(array $dashboardConfig): JsonResponse
    {
        return response()->json([
            'dashboard' => $dashboardConfig,
            'widgets' => $this->getWidgetData($dashboardConfig)
        ]);
    }

    protected function getWidgetData(array $dashboardConfig): array
    {
        $widgetData = [];
        
        foreach ($dashboardConfig['widgets'] ?? [] as $widgetName => $widgetConfig) {
            $widgetData[$widgetName] = $this->generateWidgetData($widgetName, $widgetConfig);
        }
        
        return $widgetData;
    }

    protected function generateWidgetData(string $widgetName, array $widgetConfig): array
    {
        switch ($widgetConfig['type']) {
            case 'metric':
                return $this->generateMetricData($widgetName, $widgetConfig);
            case 'table':
                return $this->generateTableData($widgetName, $widgetConfig);
            case 'list':
                return $this->generateListData($widgetName, $widgetConfig);
            case 'chart':
                return $this->generateChartData($widgetName, $widgetConfig);
            default:
                return ['data' => [], 'config' => $widgetConfig];
        }
    }

    protected function generateMetricData(string $widgetName, array $widgetConfig): array
    {
        switch ($widgetName) {
            case 'BlueprintStats':
                return [
                    'data' => [
                        'value' => 'Active',
                        'status' => 'success'
                    ],
                    'config' => $widgetConfig
                ];
            default:
                return [
                    'data' => ['value' => 0],
                    'config' => $widgetConfig
                ];
        }
    }

    protected function generateTableData(string $widgetName, array $widgetConfig): array
    {
        switch ($widgetName) {
            case 'RecentGenerations':
                return [
                    'data' => [
                        [
                            'id' => 1,
                            'type' => 'Model',
                            'name' => 'User',
                            'created_at' => now()->subHours(2)->format('Y-m-d H:i:s')
                        ],
                        [
                            'id' => 2,
                            'type' => 'Controller',
                            'name' => 'UserController',
                            'created_at' => now()->subHours(4)->format('Y-m-d H:i:s')
                        ]
                    ],
                    'config' => $widgetConfig
                ];
            default:
                return [
                    'data' => [],
                    'config' => $widgetConfig
                ];
        }
    }

    protected function generateListData(string $widgetName, array $widgetConfig): array
    {
        switch ($widgetName) {
            case 'PluginOverview':
                return [
                    'data' => [
                        ['name' => 'Blueprint Auditing', 'status' => 'Active'],
                        ['name' => 'Blueprint Constraints', 'status' => 'Active'],
                        ['name' => 'Blueprint StateMachine', 'status' => 'Active']
                    ],
                    'config' => $widgetConfig
                ];
            default:
                return [
                    'data' => [],
                    'config' => $widgetConfig
                ];
        }
    }

    protected function generateChartData(string $widgetName, array $widgetConfig): array
    {
        return [
            'data' => [
                ['date' => '2024-01-01', 'value' => 10],
                ['date' => '2024-01-02', 'value' => 15],
                ['date' => '2024-01-03', 'value' => 12]
            ],
            'config' => $widgetConfig
        ];
    }

    protected function findWidget(array $dashboardConfig, string $widgetName): ?array
    {
        return $dashboardConfig['widgets'][$widgetName] ?? null;
    }
} 
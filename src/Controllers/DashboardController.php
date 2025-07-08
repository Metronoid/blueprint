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
        
        return view('blueprint::dashboard.index', [
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
        // First try to load from base_path
        $baseDashboardPath = base_path('dashboard.base.yaml');
        
        if (!file_exists($baseDashboardPath)) {
            // Fallback to stubs directory
            $baseDashboardPath = dirname(__DIR__) . '/../stubs/dashboard.base.yaml';
        }
        
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
        try {
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
        } catch (\Exception $e) {
            // If plugin manager fails, continue without plugins
        }
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
        $limit = $widgetConfig['config']['limit'] ?? 10;
        
        return [
            'data' => [
                'headers' => ['ID', 'Name', 'Created'],
                'rows' => array_fill(0, min($limit, 5), ['1', 'Test Item', '2024-01-01'])
            ],
            'config' => $widgetConfig
        ];
    }

    protected function generateListData(string $widgetName, array $widgetConfig): array
    {
        $limit = $widgetConfig['config']['limit'] ?? 10;
        
        return [
            'data' => array_fill(0, min($limit, 5), 'Test Item'),
            'config' => $widgetConfig
        ];
    }

    protected function generateChartData(string $widgetName, array $widgetConfig): array
    {
        return [
            'data' => [
                'labels' => ['Jan', 'Feb', 'Mar'],
                'datasets' => [
                    [
                        'label' => 'Data',
                        'data' => [10, 20, 30]
                    ]
                ]
            ],
            'config' => $widgetConfig
        ];
    }

    protected function findWidget(array $dashboardConfig, string $widgetName): ?array
    {
        return $dashboardConfig['widgets'][$widgetName] ?? null;
    }
} 
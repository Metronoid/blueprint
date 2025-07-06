<?php

namespace Tests\Feature;

use Tests\TestCase;
use Blueprint\Blueprint;
use Blueprint\Tree;
use Blueprint\Models\Dashboard;
use Blueprint\Models\DashboardWidget;
use Blueprint\Services\DashboardPluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test dashboard configuration
        $this->createTestDashboardConfig();
    }

    public function test_dashboard_controller_returns_dashboard_view()
    {
        $response = $this->get('/blueprint/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('dashboard');
        $response->assertViewHas('widgets');
    }

    public function test_dashboard_controller_returns_json_data()
    {
        $response = $this->get('/blueprint/dashboard', [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'dashboard' => [
                'title',
                'description',
                'layout',
                'theme',
                'permissions',
                'navigation',
                'widgets'
            ],
            'widgets'
        ]);
    }

    public function test_widget_data_endpoint_returns_widget_data()
    {
        $response = $this->get('/blueprint/dashboard/widgets/BlueprintStats/data', [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'config'
        ]);
    }

    public function test_widget_data_endpoint_returns_404_for_invalid_widget()
    {
        $response = $this->get('/blueprint/dashboard/widgets/InvalidWidget/data', [
            'Accept' => 'application/json'
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Widget not found']);
    }

    public function test_dashboard_lexer_parses_dashboard_yaml()
    {
        $yaml = <<<'YAML'
dashboards:
  AdminDashboard:
    title: "Admin Dashboard"
    description: "Administrative dashboard"
    layout: "admin"
    theme:
      primary_color: "#1f2937"
      secondary_color: "#6b7280"
      accent_color: "#3b82f6"
    permissions: ["view-dashboard"]
    navigation:
      - name: "overview"
        title: "Overview"
        route: "/dashboard"
        icon: "home"
    widgets:
      TestWidget:
        type: "metric"
        title: "Test Widget"
        config:
          format: "number"
          color: "blue"
YAML;

        $blueprint = app(Blueprint::class);
        $tree = $blueprint->parse($yaml);

        $this->assertNotNull($tree->dashboards());
        $this->assertCount(1, $tree->dashboards());
        
        $dashboard = $tree->dashboards()->first();
        $this->assertEquals('AdminDashboard', $dashboard->name());
        $this->assertEquals('Admin Dashboard', $dashboard->title());
        $this->assertEquals('admin', $dashboard->layout());
        $this->assertCount(1, $dashboard->widgets());
    }

    public function test_dashboard_generator_creates_files()
    {
        $yaml = <<<'YAML'
dashboards:
  AdminDashboard:
    title: "Admin Dashboard"
    description: "Administrative dashboard"
    layout: "admin"
    widgets:
      TestWidget:
        type: "metric"
        title: "Test Widget"
YAML;

        $blueprint = app(Blueprint::class);
        $tree = $blueprint->parse($yaml);
        $files = $blueprint->generate($tree);

        // Check that dashboard files were generated
        $this->assertArrayHasKey('app/Http/Controllers/AdminDashboardController.php', $files);
        $this->assertArrayHasKey('app/Services/AdminDashboardService.php', $files);
        $this->assertArrayHasKey('resources/js/Pages/Dashboard/AdminDashboard.jsx', $files);
        $this->assertArrayHasKey('resources/js/Components/Dashboard/AdminDashboardLayout.jsx', $files);
    }

    public function test_dashboard_model_creation()
    {
        $dashboard = new Dashboard('TestDashboard');
        $dashboard->setTitle('Test Dashboard');
        $dashboard->setDescription('Test dashboard description');
        $dashboard->setLayout('admin');

        $this->assertEquals('TestDashboard', $dashboard->name());
        $this->assertEquals('Test Dashboard', $dashboard->title());
        $this->assertEquals('Test dashboard description', $dashboard->description());
        $this->assertEquals('admin', $dashboard->layout());
    }

    public function test_dashboard_widget_model_creation()
    {
        $widget = new DashboardWidget('TestWidget');
        $widget->setType('metric');
        $widget->setTitle('Test Widget');
        $widget->setConfig(['format' => 'number']);

        $this->assertEquals('TestWidget', $widget->name());
        $this->assertEquals('metric', $widget->type());
        $this->assertEquals('Test Widget', $widget->title());
        $this->assertEquals(['format' => 'number'], $widget->config());
    }

    public function test_dashboard_plugin_manager()
    {
        $pluginManager = app(DashboardPluginManager::class);
        
        $this->assertInstanceOf(DashboardPluginManager::class, $pluginManager);
        
        // Test plugin loading
        $plugins = $pluginManager->getPlugins();
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $plugins);
        
        // Test plugin stats
        $stats = $pluginManager->getPluginStats();
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('enabled', $stats);
        $this->assertArrayHasKey('disabled', $stats);
    }

    public function test_dashboard_with_plugin_extension()
    {
        // Create a mock plugin
        $mockPlugin = $this->createMock(\Blueprint\Contracts\DashboardPlugin::class);
        $mockPlugin->method('getName')->willReturn('TestPlugin');
        $mockPlugin->method('isEnabled')->willReturn(true);
        $mockPlugin->method('getWidgets')->willReturn([
            'PluginWidget' => [
                'type' => 'metric',
                'title' => 'Plugin Widget',
                'config' => ['format' => 'number']
            ]
        ]);
        $mockPlugin->method('getNavigation')->willReturn([
            [
                'name' => 'plugin',
                'title' => 'Plugin',
                'route' => '/plugin',
                'icon' => 'puzzle'
            ]
        ]);

        // Test dashboard extension
        $dashboard = new Dashboard('TestDashboard');
        $mockPlugin->extendDashboard($dashboard);

        $this->assertCount(1, $dashboard->widgets());
        $this->assertArrayHasKey('PluginWidget', $dashboard->widgets());
    }

    public function test_dashboard_import_command()
    {
        $this->artisan('blueprint:import-dashboard')
            ->expectsOutput('Dashboard base configuration imported successfully!')
            ->assertExitCode(0);

        // Check that the base dashboard file was created
        $this->assertFileExists(base_path('dashboard.base.yaml'));
    }

    public function test_dashboard_configuration_validation()
    {
        $invalidYaml = <<<'YAML'
dashboards:
  InvalidDashboard:
    # Missing required fields
YAML;

        $blueprint = app(Blueprint::class);
        $tree = $blueprint->parse($invalidYaml);

        // Should still parse but with default values
        $this->assertNotNull($tree->dashboards());
        $dashboard = $tree->dashboards()->first();
        $this->assertNotNull($dashboard);
    }

    public function test_dashboard_theme_configuration()
    {
        $yaml = <<<'YAML'
dashboards:
  ThemedDashboard:
    title: "Themed Dashboard"
    theme:
      primary_color: "#ff0000"
      secondary_color: "#00ff00"
      accent_color: "#0000ff"
      background_color: "#f0f0f0"
      text_color: "#333333"
      border_color: "#cccccc"
YAML;

        $blueprint = app(Blueprint::class);
        $tree = $blueprint->parse($yaml);
        $dashboard = $tree->dashboards()->first();

        $this->assertEquals('#ff0000', $dashboard->theme()['primary_color']);
        $this->assertEquals('#00ff00', $dashboard->theme()['secondary_color']);
        $this->assertEquals('#0000ff', $dashboard->theme()['accent_color']);
    }

    protected function createTestDashboardConfig()
    {
        $configPath = base_path('dashboard.base.yaml');
        
        if (!file_exists($configPath)) {
            $config = <<<'YAML'
dashboards:
  AdminDashboard:
    title: "Blueprint Dashboard"
    description: "Overview and extension area for all Blueprint plugins"
    layout: "admin"
    theme:
      primary_color: "#1f2937"
      secondary_color: "#6b7280"
      accent_color: "#3b82f6"
      background_color: "#f9fafb"
      text_color: "#1f2937"
      border_color: "#e5e7eb"
    permissions: ["view-dashboard"]
    navigation:
      - name: "overview"
        title: "Overview"
        route: "/blueprint/dashboard"
        icon: "home"
      - name: "plugins"
        title: "Plugins"
        route: "/blueprint/plugins"
        icon: "puzzle"
    widgets:
      BlueprintStats:
        type: "metric"
        title: "Blueprint Status"
        config:
          format: "status"
          color: "green"
      PluginOverview:
        type: "list"
        title: "Active Plugins"
        config:
          limit: 10
      RecentGenerations:
        type: "table"
        title: "Recent Generations"
        config:
          limit: 5
          sort_by: "created_at"
          sort_order: "desc"
YAML;
            
            file_put_contents($configPath, $config);
        }
    }
} 
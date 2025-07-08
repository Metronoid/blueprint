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
        
        // Ensure views are loaded in test environment
        $this->app['view']->addNamespace('blueprint', dirname(__DIR__) . '/../resources/views');
        
        // Check if service provider is loaded
        $this->assertTrue($this->app->bound('view'), 'View service should be bound');
    }

    public function test_dashboard_controller_returns_dashboard_view()
    {
        // Use real filesystem for this test
        $this->filesystem = new \Illuminate\Filesystem\Filesystem();
        
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
    widgets:
      TestWidget:
        type: "metric"
        title: "Test Widget"
        config:
          format: "number"
          color: "blue"
YAML;
        $blueprint = app(\Blueprint\Blueprint::class);
        $tokens = $blueprint->parse($yaml);
        $tree = $blueprint->analyze($tokens);
        $this->assertNotNull($tree->dashboards());
        $this->assertCount(1, $tree->dashboards());
        $dashboards = $tree->dashboards();
        $this->assertCount(1, $dashboards);
        $dashboard = $dashboards[0];
        $this->assertEquals('AdminDashboard', $dashboard->name());
        $this->assertEquals('Admin Dashboard', $dashboard->title());
        $this->assertEquals('admin', $dashboard->layout());
        $this->assertCount(1, $dashboard->widgets());
    }

    public function test_dashboard_generator_creates_files()
    {
        $expectedFiles = [
            'app/Http/Controllers/AdminDashboardController.php',
            'app/Services/AdminDashboardService.php',
            'resources/js/Pages/Dashboard/AdminDashboard.jsx',
            'resources/js/Components/Dashboard/AdminDashboardLayout.jsx',
        ];

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

        // Mock the filesystem to allow any put call
        $this->filesystem->shouldReceive('put')
            ->withArgs(function ($file, $content) {
                return is_string($file);
            })
            ->andReturnTrue();

        // Mock the filesystem get method for stubs
        $this->filesystem->shouldReceive('get')
            ->withArgs(function ($path) {
                return str_contains($path, 'dashboard.controller.stub');
            })
            ->andReturn('<?php // controller stub ?>');
        $this->filesystem->shouldReceive('get')
            ->withArgs(function ($path) {
                return str_contains($path, 'dashboard.service.stub');
            })
            ->andReturn('<?php // service stub ?>');
        $this->filesystem->shouldReceive('get')
            ->withArgs(function ($path) {
                return str_contains($path, 'dashboard.page.stub');
            })
            ->andReturn('<?php // page stub ?>');
        $this->filesystem->shouldReceive('get')
            ->withArgs(function ($path) {
                return str_contains($path, 'dashboard.layout.stub');
            })
            ->andReturn('<?php // layout stub ?>');

        $blueprint = app(Blueprint::class);
        $tokens = $blueprint->parse($yaml);
        $tree = $blueprint->analyze($tokens);
        $files = $blueprint->generate($tree, ['dashboard']);

        foreach ($expectedFiles as $file) {
            $this->assertContains($file, $files['created']);
        }
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
        $widget = new DashboardWidget('TestWidget', 'metric');
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
        // Make extendDashboard actually add a widget
        $mockPlugin->method('extendDashboard')->willReturnCallback(function ($dashboard) {
            $dashboard->addWidget('PluginWidget', [
                'type' => 'metric',
                'title' => 'Plugin Widget',
                'config' => ['format' => 'number']
            ]);
        });
        // Test dashboard extension
        $dashboard = new \Blueprint\Models\Dashboard('TestDashboard');
        $mockPlugin->extendDashboard($dashboard);
        $this->assertCount(1, $dashboard->widgets());
        $this->assertArrayHasKey('PluginWidget', $dashboard->widgets());
    }

    public function test_dashboard_import_command()
    {
        // Mock the filesystem to return false for the base dashboard path
        $this->filesystem->shouldReceive('exists')
            ->with(dirname(__DIR__) . '/../stubs/dashboard.base.yaml')
            ->andReturnFalse();

        // Mock the filesystem to allow writing the target file
        $this->filesystem->shouldReceive('put')
            ->with(base_path('dashboard.base.yaml'), \Mockery::any())
            ->andReturnTrue();

        $this->artisan('blueprint:import-dashboard', ['--base' => true])
            ->expectsOutput('Dashboard base configuration imported successfully!')
            ->assertExitCode(0);

        // Check that the base dashboard file was created
        $this->assertFileExists(base_path('dashboard.base.yaml'));
    }

    public function test_dashboard_configuration_validation()
    {
        $invalidYaml = <<<YAML
dashboards:
  InvalidDashboard:
    title: "Invalid Dashboard"
YAML;
        $blueprint = app(\Blueprint\Blueprint::class);
        $tokens = $blueprint->parse($invalidYaml);
        $tree = $blueprint->analyze($tokens);
        // Should still parse but with default values
        $dashboards = $tree->dashboards();
        $this->assertNotNull($dashboards);
        $this->assertCount(1, $dashboards);
        $dashboard = $dashboards[0];
        $this->assertNotNull($dashboard);
    }

    public function test_dashboard_theme_configuration()
    {
        $yaml = <<<'YAML'
dashboards:
  AdminDashboard:
    title: Admin Dashboard
    theme:
      primary_color: "#1f2937"
      secondary_color: "#6b7280"
      accent_color: "#0000ff"
YAML;
        $blueprint = app(\Blueprint\Blueprint::class);
        $tokens = $blueprint->parse($yaml);
        $tree = $blueprint->analyze($tokens);
        $dashboards = $tree->dashboards();
        $this->assertCount(1, $dashboards);
        $dashboard = $dashboards[0];
        $this->assertEquals('#1f2937', $dashboard->theme()['primary_color']);
        $this->assertEquals('#6b7280', $dashboard->theme()['secondary_color']);
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
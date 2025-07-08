<?php

namespace Blueprint\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ImportDashboardCommand extends Command
{
    protected $signature = 'blueprint:import-dashboard {name? : The name of the dashboard to import} {--base : Import the base dashboard configuration}';

    protected $description = 'Import a dashboard configuration into your project';

    public function handle(Filesystem $filesystem): int
    {
        $dashboardName = $this->argument('name') ?? 'AdminDashboard';
        $importBase = $this->option('base');

        if ($importBase) {
            return $this->importBaseDashboard($filesystem);
        }

        $this->info("Importing dashboard: $dashboardName");

        // Check if dashboard file exists
        $dashboardPath = base_path("dashboards/{$dashboardName}.yaml");
        if (!$filesystem->exists($dashboardPath)) {
            $this->error("Dashboard file not found: $dashboardPath");
            return 1;
        }

        // Read and parse the dashboard configuration
        $content = $filesystem->get($dashboardPath);
        $dashboardConfig = Yaml::parse($content);

        // Generate the dashboard
        $blueprint = app(\Blueprint\Blueprint::class);
        $tree = $blueprint->analyze($dashboardConfig);
        $generated = $blueprint->generate($tree, ['dashboard']);

        $this->displayGeneratedFiles($generated);

        return 0;
    }

    protected function importBaseDashboard(Filesystem $filesystem): int
    {
        $this->info('Importing base dashboard configuration...');

        // Copy base dashboard YAML
        $baseDashboardPath = dirname(__DIR__) . '/../stubs/dashboard.base.yaml';
        $targetPath = base_path('dashboard.base.yaml');

        if (!$filesystem->exists($baseDashboardPath)) {
            // Create a basic dashboard configuration if the stub doesn't exist
            $basicConfig = <<<'YAML'
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
            
            $filesystem->put($targetPath, $basicConfig);
        } else {
            $filesystem->copy($baseDashboardPath, $targetPath);
        }

        $this->info('Dashboard base configuration imported successfully!');
        $this->info("Base dashboard imported to: $targetPath");
        $this->info('You can now customize this file and run: php artisan blueprint:build dashboards/base-dashboard.yaml');

        return 0;
    }

    protected function displayGeneratedFiles(array $generated): void
    {
        if (!empty($generated['created'])) {
            $this->info('Created files:');
            foreach ($generated['created'] as $file) {
                $this->line("  ✓ $file");
            }
        }

        if (!empty($generated['skipped'])) {
            $this->warn('Skipped files:');
            foreach ($generated['skipped'] as $file) {
                $this->line("  - $file");
            }
        }

        if (!empty($generated['updated'])) {
            $this->info('Updated files:');
            foreach ($generated['updated'] as $file) {
                $this->line("  ↻ $file");
            }
        }
    }
} 
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
        $targetPath = base_path('dashboards/base-dashboard.yaml');

        if (!$filesystem->exists($baseDashboardPath)) {
            $this->error('Base dashboard file not found');
            return 1;
        }

        $filesystem->makeDirectory(dirname($targetPath), 0755, true, true);
        $filesystem->copy($baseDashboardPath, $targetPath);

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
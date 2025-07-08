<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MonitoringGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['monitoring'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        foreach ($tree->dashboards() as $dashboard) {
            $this->generateDashboardMonitoring($dashboard, $tree);
            $this->generateWidgetMonitoring($dashboard, $tree);
        }

        return $this->output;
    }

    protected function generateDashboardMonitoring($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('monitoring.dashboard.stub');
        if (!$stub) {
            return;
        }

        $path = 'app/Services/Monitoring/Dashboard/' . Str::studly($dashboard->name()) . 'Monitoring.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateDashboardMonitoringStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetMonitoring($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('monitoring.widget.stub');
            if (!$stub) {
                continue;
            }

            $path = 'app/Services/Monitoring/Widgets/' . Str::studly($widget->name()) . 'Monitoring.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetMonitoringStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateDashboardMonitoringStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Monitoring\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Monitoring',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ monitoringMethods }}' => $this->generateMonitoringMethods($dashboard),
            '{{ analyticsMethods }}' => $this->generateAnalyticsMethods($dashboard),
            '{{ imports }}' => $this->generateMonitoringImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetMonitoringStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'App\\Services\\Monitoring\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Monitoring',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ monitoringMethods }}' => $this->generateWidgetMonitoringMethods($widget),
            '{{ analyticsMethods }}' => $this->generateWidgetAnalyticsMethods($widget),
            '{{ imports }}' => $this->generateWidgetMonitoringImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateMonitoringMethods($dashboard): string
    {
        $methods = [];
        
        // Generate monitoring methods for dashboard
        $methods[] = "    public function trackDashboardView(\$userId)";
        $methods[] = "    {";
        $methods[] = "        // Track dashboard view for " . Str::studly($dashboard->name());
        $methods[] = "        \$this->logEvent('dashboard_viewed', [";
        $methods[] = "            'dashboard' => '" . Str::studly($dashboard->name()) . "',";
        $methods[] = "            'user_id' => \$userId,";
        $methods[] = "            'timestamp' => now(),";
        $methods[] = "        ]);";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function trackDashboardInteraction(\$userId, \$action)";
        $methods[] = "    {";
        $methods[] = "        // Track dashboard interaction";
        $methods[] = "        \$this->logEvent('dashboard_interaction', [";
        $methods[] = "            'dashboard' => '" . Str::studly($dashboard->name()) . "',";
        $methods[] = "            'user_id' => \$userId,";
        $methods[] = "            'action' => \$action,";
        $methods[] = "            'timestamp' => now(),";
        $methods[] = "        ]);";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateAnalyticsMethods($dashboard): string
    {
        $methods = [];
        
        // Generate analytics methods for dashboard
        $methods[] = "    public function getDashboardAnalytics(\$startDate = null, \$endDate = null)";
        $methods[] = "    {";
        $methods[] = "        // Get analytics for " . Str::studly($dashboard->name()) . " dashboard";
        $methods[] = "        return [";
        $methods[] = "            'total_views' => \$this->getTotalViews(\$startDate, \$endDate),";
        $methods[] = "            'unique_users' => \$this->getUniqueUsers(\$startDate, \$endDate),";
        $methods[] = "            'avg_session_duration' => \$this->getAverageSessionDuration(\$startDate, \$endDate),";
        $methods[] = "        ];";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateMonitoringImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for monitoring
        $imports[] = "use Illuminate\\Support\\Carbon;";
        $imports[] = "use Illuminate\\Support\\Collection;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetMonitoringMethods($widget): string
    {
        $methods = [];
        
        // Generate monitoring methods for widget
        $methods[] = "    public function trackWidgetView(\$userId)";
        $methods[] = "    {";
        $methods[] = "        // Track widget view for " . Str::studly($widget->name());
        $methods[] = "        \$this->logEvent('widget_viewed', [";
        $methods[] = "            'widget' => '" . Str::studly($widget->name()) . "',";
        $methods[] = "            'widget_type' => '" . $widget->type() . "',";
        $methods[] = "            'user_id' => \$userId,";
        $methods[] = "            'timestamp' => now(),";
        $methods[] = "        ]);";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function trackWidgetInteraction(\$userId, \$action)";
        $methods[] = "    {";
        $methods[] = "        // Track widget interaction";
        $methods[] = "        \$this->logEvent('widget_interaction', [";
        $methods[] = "            'widget' => '" . Str::studly($widget->name()) . "',";
        $methods[] = "            'widget_type' => '" . $widget->type() . "',";
        $methods[] = "            'user_id' => \$userId,";
        $methods[] = "            'action' => \$action,";
        $methods[] = "            'timestamp' => now(),";
        $methods[] = "        ]);";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateWidgetAnalyticsMethods($widget): string
    {
        $methods = [];
        
        // Generate analytics methods for widget
        $methods[] = "    public function getWidgetAnalytics(\$startDate = null, \$endDate = null)";
        $methods[] = "    {";
        $methods[] = "        // Get analytics for " . Str::studly($widget->name()) . " widget";
        $methods[] = "        return [";
        $methods[] = "            'total_views' => \$this->getWidgetTotalViews(\$startDate, \$endDate),";
        $methods[] = "            'unique_users' => \$this->getWidgetUniqueUsers(\$startDate, \$endDate),";
        $methods[] = "            'interaction_rate' => \$this->getWidgetInteractionRate(\$startDate, \$endDate),";
        $methods[] = "        ];";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateWidgetMonitoringImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget monitoring
        $imports[] = "use Illuminate\\Support\\Carbon;";
        $imports[] = "use Illuminate\\Support\\Collection;";

        return implode("\n", array_unique($imports));
    }
} 
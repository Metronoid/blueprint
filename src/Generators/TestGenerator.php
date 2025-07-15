<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class TestGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['tests'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        return $this->output;
    }

    protected function generateDashboardTests($dashboard, Tree $tree): void
    {
        $stub = $this->filesystem->stub('test.class.stub');
        if (!$stub) {
            return;
        }

        $path = 'tests/Feature/Dashboard/' . Str::studly($dashboard->name()) . 'Test.php';
        
        if ($this->filesystem->exists($path)) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateTestStub($stub, $dashboard, $tree);
        $this->create($path, $content);
    }

    protected function generateWidgetTests($dashboard, Tree $tree): void
    {
        foreach ($dashboard->widgets() as $widget) {
            $stub = $this->filesystem->stub('widget.test.stub');
            if (!$stub) {
                continue;
            }

            $path = 'tests/Feature/Dashboard/Widgets/' . Str::studly($widget->name()) . 'Test.php';
            
            if ($this->filesystem->exists($path)) {
                $this->output['skipped'][] = $path;
                continue;
            }

            $content = $this->populateWidgetTestStub($stub, $widget, $tree);
            $this->create($path, $content);
        }
    }

    protected function populateTestStub(string $stub, $dashboard, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'Tests\\Feature\\Dashboard',
            '{{ className }}' => Str::studly($dashboard->name()) . 'Test',
            '{{ dashboardName }}' => Str::studly($dashboard->name()),
            '{{ testMethods }}' => $this->generateTestMethods($dashboard),
            '{{ imports }}' => $this->generateTestImports($dashboard, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function populateWidgetTestStub(string $stub, $widget, Tree $tree): string
    {
        $replacements = [
            '{{ namespace }}' => 'Tests\\Feature\\Dashboard\\Widgets',
            '{{ className }}' => Str::studly($widget->name()) . 'Test',
            '{{ widgetName }}' => Str::studly($widget->name()),
            '{{ widgetType }}' => $widget->type(),
            '{{ testMethods }}' => $this->generateWidgetTestMethods($widget),
            '{{ imports }}' => $this->generateWidgetTestImports($widget, $tree),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateTestMethods($dashboard): string
    {
        $methods = [];
        
        // Generate test methods for dashboard
        $methods[] = "    public function test_user_can_view_dashboard()";
        $methods[] = "    {";
        $methods[] = "        \$user = User::factory()->create();";
        $methods[] = "        \$this->actingAs(\$user);";
        $methods[] = "";
        $methods[] = "        \$response = \$this->get('/dashboard/" . Str::kebab($dashboard->name()) . "');";
        $methods[] = "";
        $methods[] = "        \$response->assertStatus(200);";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function test_guest_cannot_view_dashboard()";
        $methods[] = "    {";
        $methods[] = "        \$response = \$this->get('/dashboard/" . Str::kebab($dashboard->name()) . "');";
        $methods[] = "";
        $methods[] = "        \$response->assertRedirect('/login');";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateTestImports($dashboard, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for dashboard tests
        $imports[] = "use Tests\\TestCase;";
        $imports[] = "use App\\Models\\User;";
        $imports[] = "use Illuminate\\Foundation\\Testing\\RefreshDatabase;";

        return implode("\n", array_unique($imports));
    }

    protected function generateWidgetTestMethods($widget): string
    {
        $methods = [];
        
        // Generate test methods for widget
        $methods[] = "    public function test_user_can_view_widget()";
        $methods[] = "    {";
        $methods[] = "        \$user = User::factory()->create();";
        $methods[] = "        \$this->actingAs(\$user);";
        $methods[] = "";
        $methods[] = "        \$response = \$this->get('/dashboard/widgets/" . Str::kebab($widget->name()) . "');";
        $methods[] = "";
        $methods[] = "        \$response->assertStatus(200);";
        $methods[] = "    }";
        $methods[] = "";
        $methods[] = "    public function test_guest_cannot_view_widget()";
        $methods[] = "    {";
        $methods[] = "        \$response = \$this->get('/dashboard/widgets/" . Str::kebab($widget->name()) . "');";
        $methods[] = "";
        $methods[] = "        \$response->assertRedirect('/login');";
        $methods[] = "    }";

        return implode("\n", $methods);
    }

    protected function generateWidgetTestImports($widget, Tree $tree): string
    {
        $imports = [];
        
        // Add imports for widget tests
        $imports[] = "use Tests\\TestCase;";
        $imports[] = "use App\\Models\\User;";
        $imports[] = "use Illuminate\\Foundation\\Testing\\RefreshDatabase;";

        return implode("\n", array_unique($imports));
    }
} 
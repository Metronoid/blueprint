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
        // Delegate to existing generators
        $this->delegateToExistingGenerators($tree, $overwriteMigrations);
        
        // Delegate to new specialized generators
        $this->delegateToSpecializedGenerators($dashboard, $tree, $overwriteMigrations);
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
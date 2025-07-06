<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FrontendGenerator implements Generator
{
    protected array $types = ['frontend'];

    protected array $frameworks = [
        'react' => [
            'extension' => '.jsx',
            'stub_prefix' => 'frontend.react',
            'path' => 'resources/js/components',
        ],
        'vue' => [
            'extension' => '.vue',
            'stub_prefix' => 'frontend.vue',
            'path' => 'resources/js/components',
        ],
        'svelte' => [
            'extension' => '.svelte',
            'stub_prefix' => 'frontend.svelte',
            'path' => 'resources/js/components',
        ],
        'typescript' => [
            'extension' => '.tsx',
            'stub_prefix' => 'frontend.typescript',
            'path' => 'resources/js/components',
        ],
    ];

    protected array $output = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function output(Tree $tree, $overwriteMigrations = false): array
    {
        $this->output = [];

        foreach ($tree->frontend() as $component) {
            $this->generateComponent($component);
        }

        return $this->output;
    }

    protected function generateComponent($component): void
    {
        $framework = $component->framework() ?? 'react';
        $frameworkConfig = $this->frameworks[$framework] ?? $this->frameworks['react'];

        $componentType = $component->type() ?? 'component';
        $stubName = $frameworkConfig['stub_prefix'] . '.' . $componentType;
        
        $stub = $this->filesystem->stub($stubName . '.stub');
        
        if (!$stub) {
            // Fallback to basic component stub
            $stub = $this->filesystem->stub($frameworkConfig['stub_prefix'] . '.component.stub');
        }

        if (!$stub) {
            return;
        }

        $path = $this->getComponentPath($component, $frameworkConfig);
        
        if ($this->filesystem->exists($path) && !$overwriteMigrations) {
            $this->output['skipped'][] = $path;
            return;
        }

        $content = $this->populateStub($stub, $component, $framework);
        
        $this->create($path, $content);
        
        // Generate additional files if needed
        $this->generateAdditionalFiles($component, $frameworkConfig);
    }

    protected function getComponentPath($component, array $frameworkConfig): string
    {
        $basePath = $frameworkConfig['path'];
        $name = Str::kebab($component->name());
        
        return $basePath . '/' . $name . $frameworkConfig['extension'];
    }

    protected function populateStub(string $stub, $component, string $framework): string
    {
        $replacements = [
            '{{ componentName }}' => $component->name(),
            '{{ componentNameKebab }}' => Str::kebab($component->name()),
            '{{ componentNameCamel }}' => Str::camel($component->name()),
            '{{ props }}' => $this->generateProps($component, $framework),
            '{{ state }}' => $this->generateState($component, $framework),
            '{{ methods }}' => $this->generateMethods($component, $framework),
            '{{ styles }}' => $this->generateStyles($component, $framework),
            '{{ dependencies }}' => $this->generateDependencies($component, $framework),
            '{{ layout }}' => $component->layout() ?? 'default',
            '{{ route }}' => $component->route() ?? '',
            '{{ api }}' => $this->generateApi($component, $framework),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function generateProps($component, string $framework): string
    {
        $props = $component->props();
        
        if (empty($props)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactProps($props);
            case 'vue':
                return $this->generateVueProps($props);
            case 'svelte':
                return $this->generateSvelteProps($props);
            default:
                return '';
        }
    }

    protected function generateReactProps(array $props): string
    {
        $propStrings = [];
        
        foreach ($props as $name => $type) {
            $propStrings[] = "  $name: $type";
        }
        
        return "{\n" . implode(",\n", $propStrings) . "\n}";
    }

    protected function generateVueProps(array $props): string
    {
        $propStrings = [];
        
        foreach ($props as $name => $type) {
            $propStrings[] = "    $name: $type";
        }
        
        return "{\n" . implode(",\n", $propStrings) . "\n  }";
    }

    protected function generateSvelteProps(array $props): string
    {
        $propStrings = [];
        
        foreach ($props as $name => $type) {
            $propStrings[] = "export let $name: $type;";
        }
        
        return implode("\n", $propStrings);
    }

    protected function generateState($component, string $framework): string
    {
        $state = $component->state();
        
        if (empty($state)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactState($state);
            case 'vue':
                return $this->generateVueState($state);
            case 'svelte':
                return $this->generateSvelteState($state);
            default:
                return '';
        }
    }

    protected function generateReactState(array $state): string
    {
        $stateStrings = [];
        
        foreach ($state as $name => $initialValue) {
            $stateStrings[] = "  const [$name, set" . Str::studly($name) . "] = useState($initialValue);";
        }
        
        return implode("\n", $stateStrings);
    }

    protected function generateVueState(array $state): string
    {
        $stateStrings = [];
        
        foreach ($state as $name => $initialValue) {
            $stateStrings[] = "    const $name = ref($initialValue);";
        }
        
        return implode("\n", $stateStrings);
    }

    protected function generateSvelteState(array $state): string
    {
        $stateStrings = [];
        
        foreach ($state as $name => $initialValue) {
            $stateStrings[] = "let $name = $initialValue;";
        }
        
        return implode("\n", $stateStrings);
    }

    protected function generateMethods($component, string $framework): string
    {
        $methods = $component->methods();
        
        if (empty($methods)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactMethods($methods);
            case 'vue':
                return $this->generateVueMethods($methods);
            case 'svelte':
                return $this->generateSvelteMethods($methods);
            default:
                return '';
        }
    }

    protected function generateReactMethods(array $methods): string
    {
        $methodStrings = [];
        
        foreach ($methods as $name => $body) {
            $methodStrings[] = "  const $name = () => {\n    $body\n  };";
        }
        
        return implode("\n\n", $methodStrings);
    }

    protected function generateVueMethods(array $methods): string
    {
        $methodStrings = [];
        
        foreach ($methods as $name => $body) {
            $methodStrings[] = "    const $name = () => {\n      $body\n    };";
        }
        
        return implode("\n\n", $methodStrings);
    }

    protected function generateSvelteMethods(array $methods): string
    {
        $methodStrings = [];
        
        foreach ($methods as $name => $body) {
            $methodStrings[] = "function $name() {\n  $body\n}";
        }
        
        return implode("\n\n", $methodStrings);
    }

    protected function generateStyles($component, string $framework): string
    {
        $styles = $component->styles();
        
        if (empty($styles)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactStyles($styles);
            case 'vue':
                return $this->generateVueStyles($styles);
            case 'svelte':
                return $this->generateSvelteStyles($styles);
            default:
                return '';
        }
    }

    protected function generateReactStyles(array $styles): string
    {
        $styleStrings = [];
        
        foreach ($styles as $selector => $rules) {
            $styleStrings[] = "  $selector: {";
            foreach ($rules as $property => $value) {
                $styleStrings[] = "    $property: '$value',";
            }
            $styleStrings[] = "  },";
        }
        
        return implode("\n", $styleStrings);
    }

    protected function generateVueStyles(array $styles): string
    {
        $styleStrings = [];
        
        foreach ($styles as $selector => $rules) {
            $styleStrings[] = "  $selector {";
            foreach ($rules as $property => $value) {
                $styleStrings[] = "    $property: $value;";
            }
            $styleStrings[] = "  }";
        }
        
        return implode("\n", $styleStrings);
    }

    protected function generateSvelteStyles(array $styles): string
    {
        $styleStrings = [];
        
        foreach ($styles as $selector => $rules) {
            $styleStrings[] = "  $selector {";
            foreach ($rules as $property => $value) {
                $styleStrings[] = "    $property: $value;";
            }
            $styleStrings[] = "  }";
        }
        
        return implode("\n", $styleStrings);
    }

    protected function generateDependencies($component, string $framework): string
    {
        $dependencies = $component->dependencies();
        
        if (empty($dependencies)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactDependencies($dependencies);
            case 'vue':
                return $this->generateVueDependencies($dependencies);
            case 'svelte':
                return $this->generateSvelteDependencies($dependencies);
            default:
                return '';
        }
    }

    protected function generateReactDependencies(array $dependencies): string
    {
        $importStrings = [];
        
        foreach ($dependencies as $dependency) {
            if (is_string($dependency)) {
                $importStrings[] = "import $dependency from '$dependency';";
            } elseif (is_array($dependency)) {
                $name = $dependency['name'] ?? '';
                $from = $dependency['from'] ?? '';
                $importStrings[] = "import { $name } from '$from';";
            }
        }
        
        return implode("\n", $importStrings);
    }

    protected function generateVueDependencies(array $dependencies): string
    {
        $importStrings = [];
        
        foreach ($dependencies as $dependency) {
            if (is_string($dependency)) {
                $importStrings[] = "import $dependency from '$dependency';";
            } elseif (is_array($dependency)) {
                $name = $dependency['name'] ?? '';
                $from = $dependency['from'] ?? '';
                $importStrings[] = "import { $name } from '$from';";
            }
        }
        
        return implode("\n", $importStrings);
    }

    protected function generateSvelteDependencies(array $dependencies): string
    {
        $importStrings = [];
        
        foreach ($dependencies as $dependency) {
            if (is_string($dependency)) {
                $importStrings[] = "import $dependency from '$dependency';";
            } elseif (is_array($dependency)) {
                $name = $dependency['name'] ?? '';
                $from = $dependency['from'] ?? '';
                $importStrings[] = "import { $name } from '$from';";
            }
        }
        
        return implode("\n", $importStrings);
    }

    protected function generateApi($component, string $framework): string
    {
        $api = $component->api();
        
        if (empty($api)) {
            return '';
        }

        switch ($framework) {
            case 'react':
            case 'typescript':
                return $this->generateReactApi($api);
            case 'vue':
                return $this->generateVueApi($api);
            case 'svelte':
                return $this->generateSvelteApi($api);
            default:
                return '';
        }
    }

    protected function generateReactApi(array $api): string
    {
        $apiStrings = [];
        
        foreach ($api as $method => $config) {
            $url = $config['url'] ?? '';
            $apiStrings[] = "  const $method = async () => {";
            $apiStrings[] = "    const response = await fetch('$url');";
            $apiStrings[] = "    return response.json();";
            $apiStrings[] = "  };";
        }
        
        return implode("\n\n", $apiStrings);
    }

    protected function generateVueApi(array $api): string
    {
        $apiStrings = [];
        
        foreach ($api as $method => $config) {
            $url = $config['url'] ?? '';
            $apiStrings[] = "    const $method = async () => {";
            $apiStrings[] = "      const response = await fetch('$url');";
            $apiStrings[] = "      return response.json();";
            $apiStrings[] = "    };";
        }
        
        return implode("\n\n", $apiStrings);
    }

    protected function generateSvelteApi(array $api): string
    {
        $apiStrings = [];
        
        foreach ($api as $method => $config) {
            $url = $config['url'] ?? '';
            $apiStrings[] = "async function $method() {";
            $apiStrings[] = "  const response = await fetch('$url');";
            $apiStrings[] = "  return response.json();";
            $apiStrings[] = "}";
        }
        
        return implode("\n\n", $apiStrings);
    }

    protected function generateAdditionalFiles($component, array $frameworkConfig): void
    {
        // Generate CSS/SCSS files if needed
        if (!empty($component->styles())) {
            $this->generateStyleFile($component, $frameworkConfig);
        }

        // Generate test files if configured
        if (config('blueprint.generate_frontend_tests', true)) {
            $this->generateTestFile($component, $frameworkConfig);
        }

        // Generate story files for Storybook if configured
        if (config('blueprint.generate_stories', false)) {
            $this->generateStoryFile($component, $frameworkConfig);
        }
    }

    protected function generateStyleFile($component, array $frameworkConfig): void
    {
        $name = Str::kebab($component->name());
        $path = $frameworkConfig['path'] . '/' . $name . '.css';
        
        if ($this->filesystem->exists($path)) {
            return;
        }

        $stub = $this->filesystem->stub('frontend.css.stub');
        if ($stub) {
            $content = str_replace('{{ componentName }}', $name, $stub);
            $this->create($path, $content);
        }
    }

    protected function generateTestFile($component, array $frameworkConfig): void
    {
        $name = Str::kebab($component->name());
        $path = $frameworkConfig['path'] . '/' . $name . '.test' . $frameworkConfig['extension'];
        
        if ($this->filesystem->exists($path)) {
            return;
        }

        $stub = $this->filesystem->stub('frontend.test.stub');
        if ($stub) {
            $content = str_replace('{{ componentName }}', $component->name(), $stub);
            $this->create($path, $content);
        }
    }

    protected function generateStoryFile($component, array $frameworkConfig): void
    {
        $name = Str::kebab($component->name());
        $path = $frameworkConfig['path'] . '/' . $name . '.stories' . $frameworkConfig['extension'];
        
        if ($this->filesystem->exists($path)) {
            return;
        }

        $stub = $this->filesystem->stub('frontend.stories.stub');
        if ($stub) {
            $content = str_replace('{{ componentName }}', $component->name(), $stub);
            $this->create($path, $content);
        }
    }

    protected function create(string $path, string $content): void
    {
        $this->filesystem->makeDirectory(dirname($path), 0755, true, true);
        $this->filesystem->put($path, $content);
        $this->output['created'][] = $path;
    }

    public function types(): array
    {
        return $this->types;
    }
} 
<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

abstract class PluginTestCase extends TestCase
{
    protected Filesystem $filesystem;
    protected PluginManager $pluginManager;
    protected GeneratorRegistry $generatorRegistry;
    protected ConfigValidator $configValidator;
    protected DependencyResolver $dependencyResolver;
    protected LoadOrderManager $loadOrderManager;
    protected array $mockPlugins = [];
    protected array $mockGenerators = [];
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize components
        $this->filesystem = new Filesystem();
        $this->configValidator = new ConfigValidator();
        $this->dependencyResolver = new DependencyResolver();
        $this->loadOrderManager = new LoadOrderManager($this->dependencyResolver);
        $this->generatorRegistry = new GeneratorRegistry($this->filesystem);
        
        // Create plugin manager with mocked discovery
        $mockDiscovery = $this->createMock(\Blueprint\Contracts\PluginDiscovery::class);
        $mockDiscovery->method('discover')->willReturn([]);
        
        $mockEventDispatcher = $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class);
        
        $this->pluginManager = new PluginManager($mockDiscovery, $mockEventDispatcher);
        $this->pluginManager->setGeneratorRegistry($this->generatorRegistry);
        $this->pluginManager->setConfigValidator($this->configValidator);
        
        // Set up temporary directory
        $this->tempDir = sys_get_temp_dir() . '/blueprint-plugin-test-' . uniqid();
        $this->filesystem->makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->deleteDirectory($this->tempDir);
        }
        
        // Clear mocks
        $this->mockPlugins = [];
        $this->mockGenerators = [];
        
        parent::tearDown();
    }

    /**
     * Create a mock plugin with specified properties.
     */
    protected function createMockPlugin(
        string $name,
        string $version = '1.0.0',
        array $dependencies = [],
        array $configSchema = []
    ): MockObject {
        $plugin = $this->createMock(Plugin::class);
        
        $plugin->method('getName')->willReturn($name);
        $plugin->method('getVersion')->willReturn($version);
        $plugin->method('getDependencies')->willReturn($dependencies);
        $plugin->method('getConfigSchema')->willReturn($configSchema);
        $plugin->method('getDescription')->willReturn("Test plugin: {$name}");
        $plugin->method('getAuthor')->willReturn('Test Author');
        $plugin->method('isCompatible')->willReturn(true);
        
        $this->mockPlugins[$name] = $plugin;
        
        return $plugin;
    }

    /**
     * Create a mock plugin generator.
     */
    protected function createMockPluginGenerator(
        Plugin $plugin,
        string $name,
        array $types = ['models'],
        int $priority = 100
    ): MockObject {
        $generator = $this->createMock(PluginGenerator::class);
        
        $generator->method('getPlugin')->willReturn($plugin);
        $generator->method('getName')->willReturn($name);
        $generator->method('types')->willReturn($types);
        $generator->method('getPriority')->willReturn($priority);
        $generator->method('shouldRun')->willReturn(true);
        $generator->method('canHandle')->willReturnCallback(fn($type) => in_array($type, $types));
        $generator->method('getConfig')->willReturn([]);
        $generator->method('output')->willReturn([]);
        
        $this->mockGenerators[$name] = $generator;
        
        return $generator;
    }

    /**
     * Create a mock core generator.
     */
    protected function createMockCoreGenerator(
        string $name,
        array $types = ['models']
    ): MockObject {
        $generator = $this->createMock(Generator::class);
        
        $generator->method('types')->willReturn($types);
        $generator->method('output')->willReturn([]);
        
        return $generator;
    }

    /**
     * Create a test tree with sample data.
     */
    protected function createTestTree(array $models = [], array $controllers = []): Tree
    {
        $treeData = [
            'models' => $models,
            'controllers' => $controllers,
            'cache' => [],
            'components' => [],
            'policies' => [],
            'seeders' => []
        ];
        
        return new Tree($treeData);
    }

    /**
     * Register a plugin for testing.
     */
    protected function registerPlugin(Plugin $plugin): void
    {
        $this->pluginManager->registerPlugin($plugin);
    }

    /**
     * Register a plugin generator for testing.
     */
    protected function registerPluginGenerator(PluginGenerator $generator): void
    {
        $this->generatorRegistry->registerPluginGenerator($generator);
    }

    /**
     * Register a core generator for testing.
     */
    protected function registerCoreGenerator(string $type, Generator $generator): void
    {
        $this->generatorRegistry->registerGenerator($type, $generator);
    }

    /**
     * Assert that a plugin is registered.
     */
    protected function assertPluginRegistered(string $name): void
    {
        $this->assertTrue(
            $this->pluginManager->hasPlugin($name),
            "Plugin '{$name}' is not registered"
        );
    }

    /**
     * Assert that a plugin generator is registered.
     */
    protected function assertPluginGeneratorRegistered(string $pluginName, string $generatorName): void
    {
        $generators = $this->generatorRegistry->getGeneratorsByPlugin($pluginName);
        $generatorNames = array_map(fn($gen) => $gen->getName(), $generators);
        
        $this->assertContains(
            $generatorName,
            $generatorNames,
            "Generator '{$generatorName}' is not registered for plugin '{$pluginName}'"
        );
    }

    /**
     * Assert that plugins are loaded in the correct order.
     */
    protected function assertPluginLoadOrder(array $expectedOrder): void
    {
        // Trigger load order calculation
        $loadOrderManager = $this->pluginManager->getLoadOrderManager();
        if ($loadOrderManager) {
            $loadOrderManager->calculateLoadOrder();
        }
        
        $actualOrder = $this->pluginManager->getPluginLoadOrder();
        
        $this->assertEquals(
            $expectedOrder,
            $actualOrder,
            'Plugin load order does not match expected order'
        );
    }

    /**
     * Assert that plugin dependencies are satisfied.
     */
    protected function assertPluginDependenciesSatisfied(string $pluginName): void
    {
        $this->assertTrue(
            $this->pluginManager->arePluginDependenciesSatisfied($pluginName),
            "Plugin '{$pluginName}' has unsatisfied dependencies"
        );
    }

    /**
     * Assert that a plugin has missing dependencies.
     */
    protected function assertPluginHasMissingDependencies(string $pluginName, array $expectedMissing = []): void
    {
        $missing = $this->pluginManager->getPluginMissingDependencies($pluginName);
        
        $this->assertNotEmpty($missing, "Plugin '{$pluginName}' should have missing dependencies");
        
        if (!empty($expectedMissing)) {
            $missingNames = array_column($missing, 'name');
            foreach ($expectedMissing as $expected) {
                $this->assertContains($expected, $missingNames, "Expected missing dependency '{$expected}' not found");
            }
        }
    }

    /**
     * Assert that generator output contains expected files.
     */
    protected function assertGeneratorOutput(array $output, string $type, array $expectedFiles): void
    {
        $this->assertArrayHasKey($type, $output, "Generator output missing type '{$type}'");
        
        $actualFiles = $output[$type];
        if (!is_array($actualFiles)) {
            $actualFiles = [$actualFiles];
        }
        
        foreach ($expectedFiles as $expectedFile) {
            $found = false;
            foreach ($actualFiles as $actualFile) {
                if (is_string($actualFile) && str_contains($actualFile, $expectedFile)) {
                    $found = true;
                    break;
                }
                if (is_array($actualFile) && isset($actualFile['path']) && str_contains($actualFile['path'], $expectedFile)) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue($found, "Expected file '{$expectedFile}' not found in generator output");
        }
    }

    /**
     * Create a temporary file for testing.
     */
    protected function createTempFile(string $path, string $content = ''): string
    {
        $fullPath = $this->tempDir . '/' . $path;
        $directory = dirname($fullPath);
        
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }
        
        $this->filesystem->put($fullPath, $content);
        
        return $fullPath;
    }

    /**
     * Create a temporary directory for testing.
     */
    protected function createTempDirectory(string $path): string
    {
        $fullPath = $this->tempDir . '/' . $path;
        $this->filesystem->makeDirectory($fullPath, 0755, true);
        
        return $fullPath;
    }

    /**
     * Get the contents of a temporary file.
     */
    protected function getTempFileContents(string $path): string
    {
        $fullPath = $this->tempDir . '/' . $path;
        
        if (!$this->filesystem->exists($fullPath)) {
            throw new \RuntimeException("Temp file '{$path}' does not exist");
        }
        
        return $this->filesystem->get($fullPath);
    }

    /**
     * Assert that a temporary file exists.
     */
    protected function assertTempFileExists(string $path): void
    {
        $fullPath = $this->tempDir . '/' . $path;
        $this->assertTrue(
            $this->filesystem->exists($fullPath),
            "Temp file '{$path}' does not exist"
        );
    }

    /**
     * Assert that a temporary file contains expected content.
     */
    protected function assertTempFileContains(string $path, string $expectedContent): void
    {
        $content = $this->getTempFileContents($path);
        $this->assertStringContainsString(
            $expectedContent,
            $content,
            "Temp file '{$path}' does not contain expected content"
        );
    }

    /**
     * Mock the configuration for a plugin.
     */
    protected function mockPluginConfig(string $pluginName, array $config): void
    {
        $this->pluginManager->setPluginConfig($pluginName, $config);
    }

    /**
     * Mock the configuration schema for a plugin.
     */
    protected function mockPluginConfigSchema(string $pluginName, array $schema): void
    {
        $this->configValidator->registerSchema($pluginName, $schema);
    }

    /**
     * Simulate plugin discovery with mock manifests.
     */
    protected function simulatePluginDiscovery(array $manifests): void
    {
        foreach ($manifests as $manifest) {
            if (isset($manifest['class']) && class_exists($manifest['class'])) {
                $plugin = new $manifest['class']();
                $this->registerPlugin($plugin);
            }
        }
    }

    /**
     * Get the plugin manager for advanced testing.
     */
    protected function getPluginManager(): PluginManager
    {
        return $this->pluginManager;
    }

    /**
     * Get the generator registry for advanced testing.
     */
    protected function getGeneratorRegistry(): GeneratorRegistry
    {
        return $this->generatorRegistry;
    }

    /**
     * Get the dependency resolver for advanced testing.
     */
    protected function getDependencyResolver(): DependencyResolver
    {
        return $this->dependencyResolver;
    }

    /**
     * Get the load order manager for advanced testing.
     */
    protected function getLoadOrderManager(): LoadOrderManager
    {
        return $this->loadOrderManager;
    }

    /**
     * Get the config validator for advanced testing.
     */
    protected function getConfigValidator(): ConfigValidator
    {
        return $this->configValidator;
    }

    /**
     * Test helper to verify plugin system integration.
     */
    protected function assertPluginSystemIntegration(): void
    {
        // Verify all components are properly connected
        $this->assertNotNull($this->pluginManager->getGeneratorRegistry());
        $this->assertNotNull($this->pluginManager->getConfigValidator());
        $this->assertNotNull($this->pluginManager->getLoadOrderManager());
        
        // Verify basic functionality
        $stats = $this->pluginManager->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_plugins', $stats);
    }

    /**
     * Create a sample plugin class for testing.
     */
    protected function createSamplePluginClass(string $name, array $dependencies = []): string
    {
        $className = 'Test' . $name . 'Plugin';
        $classCode = "
        class {$className} extends Blueprint\Plugin\AbstractPlugin
        {
            protected string \$name = '{$name}';
            protected string \$version = '1.0.0';
            protected string \$description = 'Test plugin for {$name}';
            protected array \$dependencies = " . var_export($dependencies, true) . ";
            
            public function register(): void
            {
                // Test registration
            }
            
            public function boot(): void
            {
                // Test boot
            }
        }
        ";
        
        eval($classCode);
        
        return $className;
    }
} 
<?php

namespace Tests\Feature\Plugin;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Plugin\AbstractPluginGenerator;
use Blueprint\Plugin\CompositeGenerator;
use Blueprint\Plugin\ExtendableGenerator;
use Blueprint\Plugin\GeneratorRegistry;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class GeneratorRegistryTest extends TestCase
{
    private GeneratorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $filesystem = $this->createMock(Filesystem::class);
        $this->registry = new GeneratorRegistry($filesystem);
    }

    /** @test */
    public function it_can_register_core_generators()
    {
        $generator = $this->createMockGenerator(['models']);
        
        $this->registry->registerGenerator('models', $generator);
        
        $this->assertSame($generator, $this->registry->getCoreGenerator('models'));
        $this->assertTrue($this->registry->supportsType('models'));
    }

    /** @test */
    public function it_can_register_plugin_generators()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $generator = $this->createMockPluginGenerator($plugin, ['models']);
        
        $this->registry->registerPluginGenerator($generator);
        
        $pluginGenerators = $this->registry->getPluginGenerators();
        $this->assertCount(1, $pluginGenerators);
        $this->assertSame($generator, $pluginGenerators[0]);
    }

    /** @test */
    public function it_can_get_generators_by_type()
    {
        $coreGenerator = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $pluginGenerator = $this->createMockPluginGenerator($plugin, ['models']);
        
        $this->registry->registerGenerator('models', $coreGenerator);
        $this->registry->registerPluginGenerator($pluginGenerator);
        
        $generators = $this->registry->getGeneratorsForType('models');
        $this->assertCount(2, $generators);
        $this->assertContains($coreGenerator, $generators);
        $this->assertContains($pluginGenerator, $generators);
    }

    /** @test */
    public function it_can_get_plugin_generators_by_type_sorted_by_priority()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $highPriorityGenerator = $this->createMockPluginGenerator($plugin, ['models'], 200, 'HighPriorityGenerator');
        $lowPriorityGenerator = $this->createMockPluginGenerator($plugin, ['models'], 50, 'LowPriorityGenerator');
        
        $this->registry->registerPluginGenerator($lowPriorityGenerator);
        $this->registry->registerPluginGenerator($highPriorityGenerator);
        
        $generators = $this->registry->getPluginGeneratorsForType('models');
        $this->assertCount(2, $generators);
        $this->assertSame($highPriorityGenerator, $generators[0]);
        $this->assertSame($lowPriorityGenerator, $generators[1]);
    }

    /** @test */
    public function it_can_unregister_plugin_generators()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $generator = $this->createMockPluginGenerator($plugin, ['models']);
        
        $this->registry->registerPluginGenerator($generator);
        $this->assertCount(1, $this->registry->getPluginGenerators());
        
        $this->registry->unregisterPluginGenerator('test-plugin', $generator->getName());
        $this->assertCount(0, $this->registry->getPluginGenerators());
    }

    /** @test */
    public function it_can_unregister_all_generators_from_plugin()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $generator1 = $this->createMockPluginGenerator($plugin, ['models'], 100, 'ModelGenerator');
        $generator2 = $this->createMockPluginGenerator($plugin, ['controllers'], 100, 'ControllerGenerator');
        
        $this->registry->registerPluginGenerator($generator1);
        $this->registry->registerPluginGenerator($generator2);
        $this->assertCount(2, $this->registry->getPluginGenerators());
        
        $this->registry->unregisterPlugin('test-plugin');
        $this->assertCount(0, $this->registry->getPluginGenerators());
    }

    /** @test */
    public function it_can_create_extendable_generator()
    {
        $coreGenerator = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $extensions = [
            function ($output, $tree, $generator) {
                $output['extended'] = true;
                return $output;
            }
        ];
        
        $this->registry->registerGenerator('models', $coreGenerator);
        
        $extendableGenerator = $this->registry->createExtendableGenerator('models', $plugin, $extensions);
        
        $this->assertInstanceOf(ExtendableGenerator::class, $extendableGenerator);
        $this->assertSame($coreGenerator, $extendableGenerator->getBaseGenerator());
    }

    /** @test */
    public function it_can_extend_core_generator()
    {
        $coreGenerator = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $extensions = [
            function ($output, $tree, $generator) {
                $output['extended'] = true;
                return $output;
            }
        ];
        
        $this->registry->registerGenerator('models', $coreGenerator);
        
        $result = $this->registry->extendCoreGenerator('models', $plugin, $extensions);
        
        $this->assertTrue($result);
        $this->assertInstanceOf(ExtendableGenerator::class, $this->registry->getCoreGenerator('models'));
    }

    /** @test */
    public function it_can_create_composite_for_type()
    {
        $generator1 = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $generator2 = $this->createMockPluginGenerator($plugin, ['models']);
        
        $this->registry->registerGenerator('models', $generator1);
        $this->registry->registerPluginGenerator($generator2);
        
        $composite = $this->registry->createCompositeForType('models');
        
        $this->assertInstanceOf(CompositeGenerator::class, $composite);
        $this->assertCount(2, $composite->getGenerators());
    }

    /** @test */
    public function it_can_get_active_generators_for_tree()
    {
        $tree = $this->createMockTree();
        $coreGenerator = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $pluginGenerator = $this->createMockPluginGenerator($plugin, ['models']);
        
        $pluginGenerator->expects($this->once())
            ->method('shouldRun')
            ->with($tree)
            ->willReturn(true);
        
        $this->registry->registerGenerator('models', $coreGenerator);
        $this->registry->registerPluginGenerator($pluginGenerator);
        
        $activeGenerators = $this->registry->getActiveGenerators($tree);
        
        $this->assertCount(2, $activeGenerators);
        $this->assertContains($coreGenerator, $activeGenerators);
        $this->assertContains($pluginGenerator, $activeGenerators);
    }

    /** @test */
    public function it_provides_registry_statistics()
    {
        $coreGenerator = $this->createMockGenerator(['models']);
        $plugin = $this->createMockPlugin('test-plugin');
        $pluginGenerator = $this->createMockPluginGenerator($plugin, ['controllers']);
        
        $this->registry->registerGenerator('models', $coreGenerator);
        $this->registry->registerPluginGenerator($pluginGenerator);
        
        $stats = $this->registry->getStats();
        
        $this->assertEquals(1, $stats['core_generators']);
        $this->assertEquals(1, $stats['plugin_generators']);
        $this->assertEquals(2, $stats['total_generators']);
        $this->assertEquals(1, $stats['plugins_with_generators']);
        $this->assertEquals(2, $stats['supported_types']);
        $this->assertArrayHasKey('generators_by_plugin', $stats);
        $this->assertArrayHasKey('generators_by_type', $stats);
    }

    private function createMockGenerator(array $types): Generator
    {
        $generator = $this->createMock(Generator::class);
        $generator->method('types')->willReturn($types);
        return $generator;
    }

    private function createMockPlugin(string $name): Plugin
    {
        $plugin = $this->createMock(Plugin::class);
        $plugin->method('getName')->willReturn($name);
        return $plugin;
    }

    private function createMockPluginGenerator(Plugin $plugin, array $types, int $priority = 100, string $name = 'TestGenerator'): PluginGenerator
    {
        $generator = $this->createMock(PluginGenerator::class);
        $generator->method('getPlugin')->willReturn($plugin);
        $generator->method('types')->willReturn($types);
        $generator->method('getPriority')->willReturn($priority);
        $generator->method('getName')->willReturn($name);
        $generator->method('canHandle')->willReturnCallback(fn($type) => in_array($type, $types));
        return $generator;
    }

    private function createMockTree(): Tree
    {
        return $this->createMock(Tree::class);
    }
} 
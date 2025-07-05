<?php

namespace Tests\Feature\Plugin;

use Blueprint\Plugin\LoadOrderManager;
use Blueprint\Plugin\DependencyResolver;
use Blueprint\Plugin\PluginTestCase;
use Blueprint\Exceptions\ValidationException;

class LoadOrderManagerTest extends PluginTestCase
{
    private LoadOrderManager $manager;
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DependencyResolver();
        $this->manager = new LoadOrderManager($this->resolver);
    }

    /** @test */
    public function it_can_calculate_load_order_for_simple_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $loadOrder = $this->manager->calculateLoadOrder();

        $this->assertCount(2, $loadOrder);
        $this->assertEquals('plugin-a', $loadOrder[0]->getName());
        $this->assertEquals('plugin-b', $loadOrder[1]->getName());
    }

    /** @test */
    public function it_respects_priority_within_dependency_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0');
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA, 10); // Lower priority
        $this->manager->addPlugin($pluginB, 100); // Higher priority
        $this->manager->addPlugin($pluginC, 50);

        $loadOrder = $this->manager->calculateLoadOrder();
        $loadOrderNames = array_map(fn($p) => $p->getName(), $loadOrder);

        // plugin-b should come first due to higher priority and no dependencies
        // plugin-a should come before plugin-c due to dependency
        $this->assertEquals('plugin-b', $loadOrderNames[0]);
        $this->assertEquals('plugin-a', $loadOrderNames[1]);
        $this->assertEquals('plugin-c', $loadOrderNames[2]);
    }

    /** @test */
    public function it_can_load_plugins_in_correct_order()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        // Set up expectations for register and boot methods
        $pluginA->expects($this->once())->method('register');
        $pluginA->expects($this->once())->method('boot');
        $pluginB->expects($this->once())->method('register');
        $pluginB->expects($this->once())->method('boot');

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $result = $this->manager->loadPlugins();

        $this->assertCount(2, $result['loaded']);
        $this->assertCount(0, $result['failed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(['plugin-a', 'plugin-b'], $result['loaded']);
    }

    /** @test */
    public function it_handles_plugin_loading_failures_gracefully()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0');

        // Make plugin-a throw an exception during boot
        $pluginA->expects($this->once())->method('register');
        $pluginA->expects($this->once())->method('boot')
            ->willThrowException(new \Exception('Boot failed'));

        // plugin-b should still load successfully
        $pluginB->expects($this->once())->method('register');
        $pluginB->expects($this->once())->method('boot');

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $result = $this->manager->loadPlugins();

        $this->assertCount(1, $result['loaded']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(['plugin-b'], $result['loaded']);
        $this->assertEquals('plugin-a', $result['failed'][0]['plugin']);
    }

    /** @test */
    public function it_can_check_if_plugin_is_loaded()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0');

        $this->manager->addPlugin($plugin);

        $this->assertFalse($this->manager->isPluginLoaded('test-plugin'));

        $this->manager->loadPlugins();

        $this->assertTrue($this->manager->isPluginLoaded('test-plugin'));
    }

    /** @test */
    public function it_can_get_loadable_plugins()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0');

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);
        $this->manager->addPlugin($pluginC);

        $loadable = $this->manager->getLoadablePlugins();
        $loadableNames = array_map(fn($p) => $p->getName(), $loadable);

        // Initially, only plugin-a and plugin-c should be loadable
        $this->assertCount(2, $loadable);
        $this->assertContains('plugin-a', $loadableNames);
        $this->assertContains('plugin-c', $loadableNames);
        $this->assertNotContains('plugin-b', $loadableNames);
    }

    /** @test */
    public function it_can_get_blocked_plugins()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $blocked = $this->manager->getBlockedPlugins();

        $this->assertCount(1, $blocked);
        $this->assertEquals('plugin-b', $blocked[0]['plugin']->getName());
        $this->assertEquals(['plugin-a'], $blocked[0]['missing_dependencies']);
    }

    /** @test */
    public function it_updates_loadable_plugins_after_loading()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        // Initially plugin-b is blocked
        $blocked = $this->manager->getBlockedPlugins();
        $this->assertCount(1, $blocked);

        // Load plugin-a
        $this->manager->loadPlugins();

        // Now plugin-b should be loadable
        $loadable = $this->manager->getLoadablePlugins();
        $this->assertCount(0, $loadable); // All plugins are loaded

        $blocked = $this->manager->getBlockedPlugins();
        $this->assertCount(0, $blocked); // No plugins are blocked
    }

    /** @test */
    public function it_can_get_next_plugin_to_load()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $nextPlugin = $this->manager->getNextPluginToLoad();
        $this->assertEquals('plugin-a', $nextPlugin->getName());

        // After loading plugin-a, next should be plugin-b
        $this->manager->loadPlugins();
        $nextPlugin = $this->manager->getNextPluginToLoad();
        $this->assertNull($nextPlugin); // All plugins loaded
    }

    /** @test */
    public function it_can_force_load_a_plugin()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0', ['blueprint/missing-plugin' => '^1.0']);

        $plugin->expects($this->once())->method('register');
        $plugin->expects($this->once())->method('boot');

        $this->manager->addPlugin($plugin);

        // This should normally fail due to missing dependency, but force load should work
        $this->manager->forceLoadPlugin($plugin);

        $this->assertTrue($this->manager->isPluginLoaded('test-plugin'));
    }

    /** @test */
    public function it_can_unload_a_plugin()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0');

        $this->manager->addPlugin($plugin);
        $this->manager->loadPlugins();

        $this->assertTrue($this->manager->isPluginLoaded('test-plugin'));

        $result = $this->manager->unloadPlugin('test-plugin');

        $this->assertTrue($result);
        $this->assertFalse($this->manager->isPluginLoaded('test-plugin'));
    }

    /** @test */
    public function it_prevents_unloading_plugins_with_dependents()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);
        $this->manager->loadPlugins();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot unload plugin');

        $this->manager->unloadPlugin('plugin-a');
    }

    /** @test */
    public function it_can_check_if_all_plugins_are_loaded()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0');

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $this->assertFalse($this->manager->areAllPluginsLoaded());

        $this->manager->loadPlugins();

        $this->assertTrue($this->manager->areAllPluginsLoaded());
    }

    /** @test */
    public function it_can_remove_plugins()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0');

        $this->manager->addPlugin($plugin);
        $loadOrder = $this->manager->calculateLoadOrder();
        $this->assertCount(1, $loadOrder);

        $this->manager->removePlugin('test-plugin');
        $loadOrder = $this->manager->calculateLoadOrder();
        $this->assertCount(0, $loadOrder);
    }

    /** @test */
    public function it_provides_comprehensive_stats()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginA);
        $this->manager->addPlugin($pluginB);

        $stats = $this->manager->getStats();

        $this->assertEquals(2, $stats['total_plugins']);
        $this->assertEquals(0, $stats['loaded_plugins']);
        $this->assertEquals(2, $stats['pending_plugins']);
        $this->assertIsInt($stats['loadable_plugins']);
        $this->assertEquals(1, $stats['loadable_plugins']); // Only plugin-a is loadable initially
        $this->assertIsInt($stats['blocked_plugins']);
        $this->assertEquals(1, $stats['blocked_plugins']); // plugin-b is blocked
        $this->assertTrue($stats['load_order_calculated']);
        $this->assertArrayHasKey('dependency_stats', $stats);
        $this->assertArrayHasKey('load_order', $stats);
        $this->assertArrayHasKey('loaded_plugin_names', $stats);
    }

    /** @test */
    public function it_can_reset_the_manager()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0');

        $this->manager->addPlugin($plugin);
        $this->manager->loadPlugins();

        $this->assertTrue($this->manager->isPluginLoaded('test-plugin'));

        $this->manager->reset();

        $this->assertFalse($this->manager->isPluginLoaded('test-plugin'));
        $this->assertCount(0, $this->manager->getLoadOrder());
    }

    /** @test */
    public function it_handles_complex_dependency_levels_with_priorities()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0', ['blueprint/plugin-b' => '^1.0']);
        $pluginD = $this->createMockPlugin('plugin-d', '1.0.0');
        $pluginE = $this->createMockPlugin('plugin-e', '1.0.0');

        // Add with different priorities
        $this->manager->addPlugin($pluginA, 50);
        $this->manager->addPlugin($pluginB, 100);
        $this->manager->addPlugin($pluginC, 75);
        $this->manager->addPlugin($pluginD, 200); // Highest priority, no dependencies
        $this->manager->addPlugin($pluginE, 25);  // Lowest priority, no dependencies

        $loadOrder = $this->manager->calculateLoadOrder();
        $loadOrderNames = array_map(fn($p) => $p->getName(), $loadOrder);

        // Level 0: plugin-d (highest priority), plugin-a, plugin-e (lowest priority)
        // Level 1: plugin-b
        // Level 2: plugin-c
        $this->assertEquals('plugin-d', $loadOrderNames[0]); // Highest priority in level 0
        $this->assertEquals('plugin-a', $loadOrderNames[1]); // Required by dependency chain
        $this->assertEquals('plugin-e', $loadOrderNames[2]); // Lowest priority in level 0
        $this->assertEquals('plugin-b', $loadOrderNames[3]); // Level 1
        $this->assertEquals('plugin-c', $loadOrderNames[4]); // Level 2
    }

    /** @test */
    public function it_validates_dependencies_before_loading()
    {
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->manager->addPlugin($pluginB);

        // Try to load plugin-b without plugin-a
        // This should fail during load order calculation
        $this->expectException(\Blueprint\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('unmet dependency');

        $this->manager->loadPlugins();
    }

    /** @test */
    public function it_skips_already_loaded_plugins()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0');

        // Set up expectations - should only be called once
        $plugin->expects($this->once())->method('register');
        $plugin->expects($this->once())->method('boot');

        $this->manager->addPlugin($plugin);

        // Load twice
        $this->manager->loadPlugins();
        $this->manager->loadPlugins();

        $this->assertTrue($this->manager->isPluginLoaded('test-plugin'));
    }

    /** @test */
    public function it_provides_access_to_dependency_resolver()
    {
        $resolver = $this->manager->getDependencyResolver();
        $this->assertInstanceOf(DependencyResolver::class, $resolver);
        $this->assertSame($this->resolver, $resolver);
    }

    /** @test */
    public function it_handles_empty_plugin_set()
    {
        $loadOrder = $this->manager->calculateLoadOrder();
        $this->assertCount(0, $loadOrder);

        $result = $this->manager->loadPlugins();
        $this->assertEquals(0, $result['total']);
        $this->assertCount(0, $result['loaded']);
        $this->assertCount(0, $result['failed']);

        $this->assertTrue($this->manager->areAllPluginsLoaded());
    }
} 
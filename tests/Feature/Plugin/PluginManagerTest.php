<?php

namespace Tests\Feature\Plugin;

use Blueprint\Plugin\PluginManager;
use Blueprint\Plugin\PluginDiscovery;
use Blueprint\Contracts\Plugin;
use Blueprint\Events\PluginRegistered;
use Blueprint\Events\PluginBooted;
use Blueprint\Events\PluginDiscovered;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PluginManagerTest extends TestCase
{
    private PluginManager $manager;
    private PluginDiscovery $discovery;
    private Dispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = \Mockery::mock(PluginDiscovery::class);
        $this->events = \Mockery::mock(Dispatcher::class);
        $this->manager = new PluginManager($this->discovery, $this->events);
    }

    #[Test]
    public function it_can_register_a_plugin(): void
    {
        $plugin = $this->createMockPlugin('test-plugin');
        
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(PluginRegistered::class));

        $this->manager->registerPlugin($plugin);

        $this->assertTrue($this->manager->hasPlugin('test-plugin'));
        $this->assertSame($plugin, $this->manager->getPlugin('test-plugin'));
    }

    #[Test]
    public function it_prevents_duplicate_plugin_registration(): void
    {
        $plugin1 = $this->createMockPlugin('test-plugin');
        $plugin2 = $this->createMockPlugin('test-plugin');
        
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(PluginRegistered::class));

        $this->manager->registerPlugin($plugin1);
        $this->manager->registerPlugin($plugin2); // Should be ignored

        $this->assertSame($plugin1, $this->manager->getPlugin('test-plugin'));
    }

    #[Test]
    public function it_can_get_all_registered_plugins(): void
    {
        $plugin1 = $this->createMockPlugin('plugin-1');
        $plugin2 = $this->createMockPlugin('plugin-2');
        
        $this->events->shouldReceive('dispatch')->twice();

        $this->manager->registerPlugin($plugin1);
        $this->manager->registerPlugin($plugin2);

        $plugins = $this->manager->getPlugins();

        $this->assertCount(2, $plugins);
        $this->assertArrayHasKey('plugin-1', $plugins);
        $this->assertArrayHasKey('plugin-2', $plugins);
    }

    #[Test]
    public function it_can_boot_all_plugins(): void
    {
        $plugin1 = $this->createMockPlugin('plugin-1');
        $plugin2 = $this->createMockPlugin('plugin-2');
        
        $plugin1->shouldReceive('boot')->once();
        $plugin2->shouldReceive('boot')->once();
        
        $this->events->shouldReceive('dispatch')
            ->times(4) // 2 for registration, 2 for booting
            ->with(\Mockery::any());

        $this->manager->registerPlugin($plugin1);
        $this->manager->registerPlugin($plugin2);
        $this->manager->bootPlugins();
    }

    #[Test]
    public function it_only_boots_plugins_once(): void
    {
        $plugin = $this->createMockPlugin('test-plugin');
        
        $plugin->shouldReceive('boot')->once();
        
        $this->events->shouldReceive('dispatch')->twice();

        $this->manager->registerPlugin($plugin);
        $this->manager->bootPlugins();
        $this->manager->bootPlugins(); // Should not boot again
    }

    #[Test]
    public function it_can_register_plugin_services(): void
    {
        $plugin1 = $this->createMockPlugin('plugin-1');
        $plugin2 = $this->createMockPlugin('plugin-2');
        
        $plugin1->shouldReceive('register')->once();
        $plugin2->shouldReceive('register')->once();
        
        $this->events->shouldReceive('dispatch')->twice();

        $this->manager->registerPlugin($plugin1);
        $this->manager->registerPlugin($plugin2);
        $this->manager->registerPluginServices();
    }

    #[Test]
    public function it_can_discover_and_register_plugins(): void
    {
        $manifests = [
            [
                'name' => 'discovered-plugin',
                'class' => 'TestPlugin\\DiscoveredPlugin',
                'version' => '1.0.0',
                'description' => 'Discovered plugin',
                'author' => 'Test Author',
            ]
        ];

        $this->discovery->shouldReceive('discover')
            ->once()
            ->andReturn($manifests);

        $this->events->shouldReceive('dispatch')
            ->atLeast(1) // At least one for registration
            ->with(\Mockery::any());

        $this->mockDiscoveredPluginClass();

        $this->manager->discoverPlugins();

        $this->assertTrue($this->manager->hasPlugin('discovered-plugin'));
    }

    #[Test]
    public function it_can_manage_plugin_configuration(): void
    {
        $config = ['setting1' => 'value1', 'setting2' => 'value2'];
        
        $this->manager->setPluginConfig('test-plugin', $config);
        
        $retrievedConfig = $this->manager->getPluginConfig('test-plugin');
        
        $this->assertEquals($config, $retrievedConfig);
    }

    #[Test]
    public function it_returns_empty_array_for_non_existent_plugin_config(): void
    {
        $config = $this->manager->getPluginConfig('non-existent-plugin');
        
        $this->assertEquals([], $config);
    }

    #[Test]
    public function it_provides_plugin_statistics(): void
    {
        $plugin1 = $this->createMockPlugin('plugin-1');
        $plugin2 = $this->createMockPlugin('plugin-2');
        
        $plugin1->shouldReceive('boot')->once();
        $plugin2->shouldReceive('boot')->once();
        
        $this->events->shouldReceive('dispatch')
            ->times(4) // 2 for registration, 2 for booting
            ->with(\Mockery::any());

        $this->manager->registerPlugin($plugin1);
        $this->manager->registerPlugin($plugin2);
        $this->manager->bootPlugins();

        $stats = $this->manager->getStats();

        $this->assertEquals(2, $stats['total_plugins']);
        $this->assertEquals(2, $stats['booted_plugins']);
        $this->assertContains('plugin-1', $stats['plugin_names']);
        $this->assertContains('plugin-2', $stats['plugin_names']);
    }

    #[Test]
    public function it_can_enable_and_disable_plugins(): void
    {
        $plugin = $this->createMockPlugin('test-plugin');
        
        $this->events->shouldReceive('dispatch')->once();

        $this->manager->registerPlugin($plugin);

        $this->assertTrue($this->manager->enablePlugin('test-plugin'));
        $this->assertTrue($this->manager->disablePlugin('test-plugin'));
        $this->assertFalse($this->manager->enablePlugin('non-existent'));
        $this->assertFalse($this->manager->disablePlugin('non-existent'));
    }

    #[Test]
    public function it_handles_plugin_boot_failures_gracefully(): void
    {
        $plugin = $this->createMockPlugin('failing-plugin');
        
        $plugin->shouldReceive('boot')
            ->once()
            ->andThrow(new \Exception('Boot failed'));
        
        $this->events->shouldReceive('dispatch')->once();

        $this->manager->registerPlugin($plugin);
        
        // Should not throw exception
        $this->manager->bootPlugins();
        
        $this->assertTrue($this->manager->hasPlugin('failing-plugin'));
    }

    #[Test]
    public function it_handles_plugin_service_registration_failures_gracefully(): void
    {
        $plugin = $this->createMockPlugin('failing-plugin');
        
        $plugin->shouldReceive('register')
            ->once()
            ->andThrow(new \Exception('Registration failed'));
        
        $this->events->shouldReceive('dispatch')->once();

        $this->manager->registerPlugin($plugin);
        
        // Should not throw exception
        $this->manager->registerPluginServices();
        
        $this->assertTrue($this->manager->hasPlugin('failing-plugin'));
    }

    private function createMockPlugin(string $name): Plugin
    {
        $plugin = \Mockery::mock(Plugin::class);
        $plugin->shouldReceive('getName')->andReturn($name);
        $plugin->shouldReceive('getVersion')->andReturn('1.0.0');
        $plugin->shouldReceive('getDescription')->andReturn('Test plugin');
        $plugin->shouldReceive('getAuthor')->andReturn('Test Author');
        $plugin->shouldReceive('getDependencies')->andReturn([]);
        $plugin->shouldReceive('getConfigSchema')->andReturn([]);
        $plugin->shouldReceive('isCompatible')->andReturn(true);
        
        return $plugin;
    }

    private function mockDiscoveredPluginClass(): void
    {
        if (!class_exists('TestPlugin\\DiscoveredPlugin')) {
            $code = '
                namespace TestPlugin {
                    use Blueprint\Contracts\Plugin;
                    class DiscoveredPlugin implements Plugin {
                        public function getName(): string { return "discovered-plugin"; }
                        public function getVersion(): string { return "1.0.0"; }
                        public function getDescription(): string { return "Discovered plugin"; }
                        public function getAuthor(): string { return "Test Author"; }
                        public function getDependencies(): array { return []; }
                        public function getConfigSchema(): array { return []; }
                        public function boot(): void {}
                        public function register(): void {}
                        public function isCompatible(string $blueprintVersion): bool { return true; }
                    }
                }
            ';
            eval($code);
        }
    }
} 
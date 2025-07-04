<?php

namespace Tests\Feature\Plugin;

use Blueprint\Plugin\PluginDiscovery;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PluginDiscoveryBasicTest extends TestCase
{
    private PluginDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new PluginDiscovery($this->files);
    }

    #[Test]
    public function it_can_instantiate_plugin_discovery(): void
    {
        $this->assertInstanceOf(PluginDiscovery::class, $this->discovery);
    }

    #[Test]
    public function it_validates_plugin_manifest_correctly(): void
    {
        // Valid manifest
        $validManifest = [
            'name' => 'test-plugin',
            'version' => '1.0.0',
            'description' => 'Test plugin',
            'class' => 'TestPlugin\\Plugin',
        ];

        $this->assertTrue($this->discovery->validateManifest($validManifest));

        // Invalid manifest - missing required fields
        $invalidManifest = [
            'name' => 'test-plugin',
            // missing version, description, class
        ];

        $this->assertFalse($this->discovery->validateManifest($invalidManifest));
    }

    #[Test]
    public function it_returns_null_for_invalid_plugin_directory(): void
    {
        $nonExistentPath = base_path('non-existent-plugin');
        
        $manifest = $this->discovery->getManifest($nonExistentPath);
        
        $this->assertNull($manifest);
    }

    #[Test]
    public function it_can_discover_from_composer_packages(): void
    {
        // This test would require setting up a mock vendor directory
        // For now, we'll just test that the method doesn't throw errors
        $plugins = $this->discovery->discoverFromComposer();
        
        $this->assertIsArray($plugins);
    }

    #[Test]
    public function it_discovers_all_plugins_when_calling_discover(): void
    {
        // Test that the discover method returns an array
        $plugins = $this->discovery->discover();
        
        $this->assertIsArray($plugins);
    }

    #[Test]
    public function it_can_discover_plugins_from_directory(): void
    {
        // Test that the method returns an array for a non-existent directory
        $plugins = $this->discovery->discoverFromDirectory(base_path('non-existent-plugins'));
        
        $this->assertIsArray($plugins);
        $this->assertEmpty($plugins);
    }

    #[Test]
    public function it_can_extract_manifest_from_composer_json(): void
    {
        $pluginPath = base_path('test-plugins/composer-plugin');
        
        // Create a composer.json with blueprint plugin configuration
        $composerData = [
            'name' => 'test/composer-plugin',
            'description' => 'Test composer plugin',
            'type' => 'blueprint-plugin',
            'version' => '1.0.0',
            'authors' => [
                ['name' => 'Test Author', 'email' => 'test@example.com']
            ],
            'extra' => [
                'blueprint-plugin' => [
                    'class' => 'TestPlugin\\ComposerPlugin'
                ]
            ]
        ];
        
        $composerJson = json_encode($composerData, JSON_PRETTY_PRINT);
        
        // Mock filesystem calls
        $this->files->expects('exists')
            ->with($pluginPath . '/blueprint.json')
            ->andReturnFalse();
        $this->files->expects('exists')
            ->with($pluginPath . '/composer.json')
            ->andReturnTrue();
        $this->files->expects('get')
            ->with($pluginPath . '/composer.json')
            ->andReturn($composerJson);

        $manifest = $this->discovery->getManifest($pluginPath);
        
        $this->assertNotNull($manifest);
        $this->assertEquals('test/composer-plugin', $manifest['name']);
        $this->assertEquals('1.0.0', $manifest['version']);
        $this->assertEquals('TestPlugin\\ComposerPlugin', $manifest['class']);
    }
} 
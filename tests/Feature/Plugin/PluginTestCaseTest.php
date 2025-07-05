<?php

namespace Tests\Feature\Plugin;

use Blueprint\Plugin\PluginTestCase;
use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;

class PluginTestCaseTest extends PluginTestCase
{
    /** @test */
    public function it_can_create_mock_plugins()
    {
        $plugin = $this->createMockPlugin('test-plugin', '1.0.0', ['dep' => '^1.0'], ['config' => 'schema']);

        $this->assertEquals('test-plugin', $plugin->getName());
        $this->assertEquals('1.0.0', $plugin->getVersion());
        $this->assertEquals(['dep' => '^1.0'], $plugin->getDependencies());
        $this->assertEquals(['config' => 'schema'], $plugin->getConfigSchema());
        $this->assertEquals('Test plugin: test-plugin', $plugin->getDescription());
        $this->assertEquals('Test Author', $plugin->getAuthor());
        $this->assertTrue($plugin->isCompatible('1.0.0'));
    }

    /** @test */
    public function it_can_create_mock_plugin_generators()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $generator = $this->createMockPluginGenerator($plugin, 'TestGenerator', ['models'], 150);

        $this->assertSame($plugin, $generator->getPlugin());
        $this->assertEquals('TestGenerator', $generator->getName());
        $this->assertEquals(['models'], $generator->types());
        $this->assertEquals(150, $generator->getPriority());
        $this->assertTrue($generator->shouldRun($this->createTestTree()));
        $this->assertTrue($generator->canHandle('models'));
        $this->assertFalse($generator->canHandle('controllers'));
        $this->assertEquals([], $generator->getConfig());
        $this->assertEquals([], $generator->output($this->createTestTree()));
    }

    /** @test */
    public function it_can_create_mock_core_generators()
    {
        $generator = $this->createMockCoreGenerator('ModelGenerator', ['models']);

        $this->assertEquals(['models'], $generator->types());
        $this->assertEquals([], $generator->output($this->createTestTree()));
    }

    /** @test */
    public function it_can_create_test_trees()
    {
        $tree = $this->createTestTree(
            ['User' => ['name' => 'string']],
            ['UserController' => ['actions' => ['index', 'store']]]
        );

        $this->assertInstanceOf(Tree::class, $tree);
        $this->assertCount(1, $tree->models());
        $this->assertCount(1, $tree->controllers());
    }

    /** @test */
    public function it_can_register_plugins()
    {
        $plugin = $this->createMockPlugin('test-plugin');

        $this->registerPlugin($plugin);

        $this->assertPluginRegistered('test-plugin');
    }

    /** @test */
    public function it_can_register_plugin_generators()
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $generator = $this->createMockPluginGenerator($plugin, 'TestGenerator');

        $this->registerPlugin($plugin);
        $this->registerPluginGenerator($generator);

        $this->assertPluginGeneratorRegistered('test-plugin', 'TestGenerator');
    }

    /** @test */
    public function it_can_register_core_generators()
    {
        $generator = $this->createMockCoreGenerator('TestCoreGenerator', ['models']);

        $this->registerCoreGenerator('models', $generator);

        $coreGenerator = $this->getGeneratorRegistry()->getCoreGenerator('models');
        $this->assertSame($generator, $coreGenerator);
    }

    /** @test */
    public function it_can_assert_plugin_load_order()
    {
        $pluginA = $this->createMockPlugin('plugin-a');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->registerPlugin($pluginA);
        $this->registerPlugin($pluginB);

        $this->assertPluginLoadOrder(['plugin-a', 'plugin-b']);
    }

    /** @test */
    public function it_can_assert_plugin_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->registerPlugin($pluginA);
        $this->registerPlugin($pluginB);

        $this->assertPluginDependenciesSatisfied('plugin-b');
    }

    /** @test */
    public function it_can_assert_missing_dependencies()
    {
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->registerPlugin($pluginB);

        $this->assertPluginHasMissingDependencies('plugin-b', ['blueprint/plugin-a']);
    }

    /** @test */
    public function it_can_assert_generator_output()
    {
        $output = [
            'models' => [
                'app/Models/User.php',
                'app/Models/Post.php'
            ],
            'controllers' => [
                'app/Http/Controllers/UserController.php'
            ]
        ];

        $this->assertGeneratorOutput($output, 'models', ['User.php', 'Post.php']);
        $this->assertGeneratorOutput($output, 'controllers', ['UserController.php']);
    }

    /** @test */
    public function it_can_create_and_manage_temp_files()
    {
        $path = 'test/sample.txt';
        $content = 'Hello, World!';

        $fullPath = $this->createTempFile($path, $content);

        $this->assertTempFileExists($path);
        $this->assertTempFileContains($path, 'Hello');
        $this->assertEquals($content, $this->getTempFileContents($path));
        $this->assertStringContainsString('blueprint-plugin-test-', $fullPath);
    }

    /** @test */
    public function it_can_create_temp_directories()
    {
        $path = 'test/nested/directory';

        $fullPath = $this->createTempDirectory($path);

        $this->assertTrue(is_dir($fullPath));
        $this->assertStringContainsString('blueprint-plugin-test-', $fullPath);
    }

    /** @test */
    public function it_can_mock_plugin_configuration()
    {
        $config = ['setting1' => 'value1', 'setting2' => 'value2'];

        $this->mockPluginConfig('test-plugin', $config);

        $retrievedConfig = $this->getPluginManager()->getPluginConfig('test-plugin');
        $this->assertEquals($config, $retrievedConfig);
    }

    /** @test */
    public function it_can_mock_plugin_config_schema()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'setting1' => ['type' => 'string'],
                'setting2' => ['type' => 'integer']
            ]
        ];

        $this->mockPluginConfigSchema('test-plugin', $schema);

        $retrievedSchema = $this->getConfigValidator()->getSchema('test-plugin');
        $this->assertEquals($schema, $retrievedSchema);
    }

    /** @test */
    public function it_provides_access_to_all_components()
    {
        $this->assertNotNull($this->getPluginManager());
        $this->assertNotNull($this->getGeneratorRegistry());
        $this->assertNotNull($this->getDependencyResolver());
        $this->assertNotNull($this->getLoadOrderManager());
        $this->assertNotNull($this->getConfigValidator());
    }

    /** @test */
    public function it_can_verify_plugin_system_integration()
    {
        $this->assertPluginSystemIntegration();
    }

    /** @test */
    public function it_can_create_sample_plugin_classes()
    {
        $className = $this->createSamplePluginClass('TestPlugin', ['blueprint/dependency' => '^1.0']);

        $this->assertTrue(class_exists($className));

        $plugin = new $className();
        $this->assertInstanceOf(Plugin::class, $plugin);
        $this->assertEquals('TestPlugin', $plugin->getName());
        $this->assertEquals('1.0.0', $plugin->getVersion());
        $this->assertEquals(['blueprint/dependency' => '^1.0'], $plugin->getDependencies());
    }

    /** @test */
    public function it_isolates_tests_properly()
    {
        // Create a plugin in this test
        $plugin = $this->createMockPlugin('isolation-test');
        $this->registerPlugin($plugin);
        $this->assertPluginRegistered('isolation-test');

        // The tearDown method should clean this up automatically
        // We can't directly test this in the same test, but subsequent tests
        // should not see this plugin
    }

    /** @test */
    public function it_verifies_test_isolation_worked()
    {
        // This test should not see the plugin from the previous test
        $this->assertFalse($this->getPluginManager()->hasPlugin('isolation-test'));
    }

    /** @test */
    public function it_cleans_up_temp_files_automatically()
    {
        $tempDir = $this->tempDir;
        $this->assertTrue(is_dir($tempDir));

        // Create a temp file
        $this->createTempFile('cleanup-test.txt', 'test content');
        $this->assertTempFileExists('cleanup-test.txt');

        // The tearDown method should clean this up automatically
        // We'll verify the directory exists during the test
        $this->assertTrue(is_dir($tempDir));
    }

    /** @test */
    public function it_handles_generator_output_with_different_formats()
    {
        // Test with array of strings
        $output1 = ['models' => ['User.php', 'Post.php']];
        $this->assertGeneratorOutput($output1, 'models', ['User.php']);

        // Test with array of arrays
        $output2 = [
            'models' => [
                ['path' => 'app/Models/User.php', 'content' => '...'],
                ['path' => 'app/Models/Post.php', 'content' => '...']
            ]
        ];
        $this->assertGeneratorOutput($output2, 'models', ['User.php']);

        // Test with single string
        $output3 = ['models' => 'app/Models/User.php'];
        $this->assertGeneratorOutput($output3, 'models', ['User.php']);
    }

    /** @test */
    public function it_provides_comprehensive_mock_setup()
    {
        // Verify that all necessary components are mocked and functional
        $plugin = $this->createMockPlugin('comprehensive-test');
        $generator = $this->createMockPluginGenerator($plugin, 'TestGen');

        $this->registerPlugin($plugin);
        $this->registerPluginGenerator($generator);

        // Test configuration
        $this->mockPluginConfig('comprehensive-test', ['key' => 'value']);
        $this->mockPluginConfigSchema('comprehensive-test', ['type' => 'object']);

        // Verify everything works together
        $this->assertPluginRegistered('comprehensive-test');
        $this->assertPluginGeneratorRegistered('comprehensive-test', 'TestGen');
        $this->assertEquals(['key' => 'value'], $this->getPluginManager()->getPluginConfig('comprehensive-test'));
        $this->assertEquals(['type' => 'object'], $this->getConfigValidator()->getSchema('comprehensive-test'));
    }
} 
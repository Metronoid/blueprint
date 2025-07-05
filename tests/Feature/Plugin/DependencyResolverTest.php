<?php

namespace Tests\Feature\Plugin;

use Blueprint\Plugin\DependencyResolver;
use Blueprint\Plugin\PluginTestCase;
use Blueprint\Exceptions\ValidationException;

class DependencyResolverTest extends PluginTestCase
{
    private DependencyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new DependencyResolver();
    }

    /** @test */
    public function it_can_resolve_simple_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $resolved = $this->resolver->resolve();

        $this->assertCount(2, $resolved);
        $this->assertEquals('plugin-a', $resolved[0]->getName());
        $this->assertEquals('plugin-b', $resolved[1]->getName());
    }

    /** @test */
    public function it_can_resolve_complex_dependency_chains()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0', ['blueprint/plugin-b' => '^1.0']);
        $pluginD = $this->createMockPlugin('plugin-d', '1.0.0', ['blueprint/plugin-a' => '^1.0', 'blueprint/plugin-c' => '^1.0']);

        $this->resolver->addPlugin($pluginD);
        $this->resolver->addPlugin($pluginC);
        $this->resolver->addPlugin($pluginB);
        $this->resolver->addPlugin($pluginA);

        $resolved = $this->resolver->resolve();
        $resolvedNames = array_map(fn($p) => $p->getName(), $resolved);

        $this->assertCount(4, $resolved);
        $this->assertEquals('plugin-a', $resolvedNames[0]);
        $this->assertEquals('plugin-b', $resolvedNames[1]);
        $this->assertEquals('plugin-c', $resolvedNames[2]);
        $this->assertEquals('plugin-d', $resolvedNames[3]);
    }

    /** @test */
    public function it_detects_circular_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0', ['blueprint/plugin-b' => '^1.0']);
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->hasCircularDependencies());

        $circular = $this->resolver->getCircularDependencies();
        $this->assertNotEmpty($circular);
    }

    /** @test */
    public function it_throws_exception_for_circular_dependencies_during_resolution()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0', ['blueprint/plugin-b' => '^1.0']);
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->resolver->resolve();
    }

    /** @test */
    public function it_validates_version_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.5.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));

        $resolved = $this->resolver->resolve();
        $this->assertCount(2, $resolved);
    }

    /** @test */
    public function it_fails_validation_for_incompatible_versions()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '2.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertFalse($this->resolver->areDependenciesSatisfied('plugin-b'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('unmet dependency');

        $this->resolver->resolve();
    }

    /** @test */
    public function it_handles_missing_dependencies()
    {
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginB);

        $this->assertFalse($this->resolver->areDependenciesSatisfied('plugin-b'));

        $missing = $this->resolver->getMissingDependencies('plugin-b');
        $this->assertCount(1, $missing);
        $this->assertEquals('blueprint/plugin-a', $missing[0]['name']);
        $this->assertEquals('^1.0', $missing[0]['constraint']);
    }

    /** @test */
    public function it_can_get_dependency_tree()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $tree = $this->resolver->getDependencyTree('plugin-b');

        $this->assertArrayHasKey('blueprint/plugin-a', $tree);
        $this->assertEquals('^1.0', $tree['blueprint/plugin-a']['constraint']);
        $this->assertTrue($tree['blueprint/plugin-a']['satisfied']);
    }

    /** @test */
    public function it_can_get_reverse_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);
        $this->resolver->addPlugin($pluginC);

        $reverseDeps = $this->resolver->getReverseDependencies('plugin-a');

        $this->assertCount(2, $reverseDeps);
        $this->assertContains('plugin-b', $reverseDeps);
        $this->assertContains('plugin-c', $reverseDeps);
    }

    /** @test */
    public function it_handles_caret_version_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.5.2');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));

        // Test that 2.0.0 would not satisfy ^1.0
        $pluginA2 = $this->createMockPlugin('plugin-a2', '2.0.0');
        $pluginB2 = $this->createMockPlugin('plugin-b2', '1.0.0', ['blueprint/plugin-a2' => '^1.0']);

        $resolver2 = new DependencyResolver();
        $resolver2->addPlugin($pluginA2);
        $resolver2->addPlugin($pluginB2);

        $this->assertFalse($resolver2->areDependenciesSatisfied('plugin-b2'));
    }

    /** @test */
    public function it_handles_tilde_version_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.5');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '~1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));

        // Test that 1.1.0 would not satisfy ~1.0
        $pluginA2 = $this->createMockPlugin('plugin-a2', '1.1.0');
        $pluginB2 = $this->createMockPlugin('plugin-b2', '1.0.0', ['blueprint/plugin-a2' => '~1.0']);

        $resolver2 = new DependencyResolver();
        $resolver2->addPlugin($pluginA2);
        $resolver2->addPlugin($pluginB2);

        $this->assertFalse($resolver2->areDependenciesSatisfied('plugin-b2'));
    }

    /** @test */
    public function it_handles_exact_version_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '1.0.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));

        // Test that 1.0.1 would not satisfy 1.0.0
        $pluginA2 = $this->createMockPlugin('plugin-a2', '1.0.1');
        $pluginB2 = $this->createMockPlugin('plugin-b2', '1.0.0', ['blueprint/plugin-a2' => '1.0.0']);

        $resolver2 = new DependencyResolver();
        $resolver2->addPlugin($pluginA2);
        $resolver2->addPlugin($pluginB2);

        $this->assertFalse($resolver2->areDependenciesSatisfied('plugin-b2'));
    }

    /** @test */
    public function it_handles_greater_than_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.5.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '>=1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));

        // Test >1.0
        $pluginB3 = $this->createMockPlugin('plugin-b3', '1.0.0', ['blueprint/plugin-a' => '>1.0']);
        $resolver3 = new DependencyResolver();
        $resolver3->addPlugin($pluginA);
        $resolver3->addPlugin($pluginB3);

        $this->assertTrue($resolver3->areDependenciesSatisfied('plugin-b3'));
    }

    /** @test */
    public function it_can_remove_plugins()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $resolved = $this->resolver->resolve();
        $this->assertCount(2, $resolved);

        $this->resolver->removePlugin('plugin-a');

        $this->assertFalse($this->resolver->areDependenciesSatisfied('plugin-b'));
    }

    /** @test */
    public function it_provides_comprehensive_stats()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0');

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);
        $this->resolver->addPlugin($pluginC);

        $stats = $this->resolver->getStats();

        $this->assertEquals(3, $stats['total_plugins']);
        $this->assertEquals(1, $stats['total_dependencies']);
        $this->assertEquals(1, $stats['plugins_with_dependencies']);
        $this->assertFalse($stats['circular_dependencies']);
        $this->assertArrayHasKey('load_order', $stats);
    }

    /** @test */
    public function it_handles_wildcard_version_constraints()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '2.5.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '*']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-b'));
    }

    /** @test */
    public function it_handles_php_version_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0', ['php' => '>=8.0']);

        $this->resolver->addPlugin($pluginA);

        // This should pass since we're running on PHP 8.0+
        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-a'));
    }

    /** @test */
    public function it_handles_extension_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0', ['ext-json' => '*']);

        $this->resolver->addPlugin($pluginA);

        // JSON extension should be available
        $this->assertTrue($this->resolver->areDependenciesSatisfied('plugin-a'));

        // Test non-existent extension
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['ext-nonexistent' => '*']);
        $this->resolver->addPlugin($pluginB);

        $this->assertFalse($this->resolver->areDependenciesSatisfied('plugin-b'));
    }

    /** @test */
    public function it_provides_detailed_failure_reasons()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '2.0.0');
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);

        $missing = $this->resolver->getMissingDependencies('plugin-b');
        $this->assertCount(1, $missing);
        $this->assertStringContainsString('Version mismatch', $missing[0]['reason']);
        $this->assertStringContainsString('found 2.0.0', $missing[0]['reason']);
        $this->assertStringContainsString('required ^1.0', $missing[0]['reason']);
    }

    /** @test */
    public function it_handles_three_way_circular_dependencies()
    {
        $pluginA = $this->createMockPlugin('plugin-a', '1.0.0', ['blueprint/plugin-c' => '^1.0']);
        $pluginB = $this->createMockPlugin('plugin-b', '1.0.0', ['blueprint/plugin-a' => '^1.0']);
        $pluginC = $this->createMockPlugin('plugin-c', '1.0.0', ['blueprint/plugin-b' => '^1.0']);

        $this->resolver->addPlugin($pluginA);
        $this->resolver->addPlugin($pluginB);
        $this->resolver->addPlugin($pluginC);

        $this->assertTrue($this->resolver->hasCircularDependencies());

        $circular = $this->resolver->getCircularDependencies();
        $this->assertNotEmpty($circular);
        $this->assertGreaterThan(0, count($circular[0])); // Should have a circular chain
    }
} 
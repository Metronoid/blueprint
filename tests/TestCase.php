<?php

namespace Tests;

use Blueprint\BlueprintServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    /** @var Filesystem */
    protected $filesystem;

    /** @var Filesystem */
    protected $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = $this->filesystem = File::spy();
    }

    protected function tearDown(): void
    {
        // Always restore error and exception handlers to prevent leaks
        try {
            restore_error_handler();
        } catch (\ErrorException $e) {
            // Already restored
        }
        try {
            restore_exception_handler();
        } catch (\ErrorException $e) {
            // Already restored
        }
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('blueprint.namespace', 'App');
        $app['config']->set('blueprint.controllers_namespace', 'Http\\Controllers');
        $app['config']->set('blueprint.models_namespace', 'Models');
        $app['config']->set('blueprint.app_path', 'app');
        $app['config']->set('blueprint.generate_phpdocs', false);
        $app['config']->set('blueprint.use_constraints', false);
        $app['config']->set('blueprint.fake_nullables', true);
        $app['config']->set('blueprint.generate_resource_collection_classes', true);
        $app['config']->set('database.default', 'testing');
        // Set a random app key for encryption
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    public function fixture(string $path)
    {
        return file_get_contents(__DIR__ . '/' . 'fixtures' . '/' . ltrim($path, '/'));
    }

    public function requireFixture(string $path)
    {
        require_once __DIR__ . '/' . 'fixtures' . '/' . ltrim($path, '/');
    }

    public function stub(string $path)
    {
        return file_get_contents(__DIR__ . '/../' . 'stubs' . '/' . ltrim($path, '/'));
    }

    protected function getPackageProviders($app)
    {
        return [
            BlueprintServiceProvider::class,
        ];
    }

    /**
     * Assert generator output arrays are equal, normalizing missing keys.
     */
    protected function assertGeneratorOutputEquals(array $expected, array $actual, string $message = ''): void
    {
        $normalize = function ($arr) {
            foreach (['created', 'updated', 'skipped'] as $key) {
                if (!array_key_exists($key, $arr)) {
                    $arr[$key] = [];
                }
            }
            ksort($arr);
            return $arr;
        };
        $this->assertEquals($normalize($expected), $normalize($actual), $message);
    }
}

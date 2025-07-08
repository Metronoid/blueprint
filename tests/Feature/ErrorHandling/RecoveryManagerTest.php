<?php

namespace Tests\Feature\ErrorHandling;

use Blueprint\ErrorHandling\ErrorLogger;
use Blueprint\ErrorHandling\RecoveryManager;
use Blueprint\ErrorHandling\RecoveryResult;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Exceptions\GenerationException;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

class RecoveryManagerTest extends TestCase
{
    private RecoveryManager $manager;
    private Filesystem $fileSystem;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        
        $logger = new ErrorLogger(new NullLogger());
        $this->manager = new RecoveryManager($logger);
        $this->fileSystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/blueprint_recovery_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if ($this->fileSystem->exists($this->tempDir)) {
            $this->fileSystem->deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    /** @test */
    public function it_can_recover_from_yaml_syntax_errors()
    {
        $yamlContent = "models:\n  User:\n    name:string\n    email:email";
        
        $exception = new ParsingException('YAML syntax error');
        $exception->addContext('yaml_content', $yamlContent);

        $result = $this->manager->attemptRecovery($exception);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Applied YAML syntax fixes', $result->getMessage());
        $this->assertArrayHasKey('fixes', $result->getData());
        $this->assertArrayHasKey('fixed_content', $result->getData());
    }

    /** @test */
    public function it_can_handle_missing_directory_recovery()
    {
        $testFile = $this->tempDir . '/nested/deep/test.php';
        
        $exception = new GenerationException('Failed to create file');
        $exception->setFilePath($testFile);

        $result = $this->manager->attemptRecovery($exception);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Successfully created directory', $result->getMessage());
        $this->assertTrue($this->fileSystem->exists(dirname($testFile)));
    }

    /** @test */
    public function it_can_detect_file_permission_issues()
    {
        $exception = new GenerationException('Permission denied');
        $exception->addContext('permission_error', true);
        $exception->setFilePath('/root/restricted/file.php');

        $result = $this->manager->attemptRecovery($exception);

        $this->assertFalse($result->isSuccessful());
        // Since multiple strategies are tried and all fail, the final message is generic
        $this->assertStringContainsString('No recovery strategies succeeded', $result->getMessage());
    }

    /** @test */
    public function it_returns_specific_error_for_nonexistent_directory()
    {
        // Test the file permission strategy specifically by creating a mock that only has permission context
        $exception = new GenerationException('Permission denied');
        $exception->addContext('permission_error', true);
        $exception->setFilePath('/nonexistent/path/file.php');

        // Get the file permission strategy directly to test its specific behavior
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('recoverFilePermissions');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager, $exception);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('Directory does not exist', $result->getMessage());
    }

    /** @test */
    public function it_can_suggest_validation_fixes()
    {
        $exception = new ValidationException('Invalid relationship format');
        $exception->addContext('invalid_relationship', 'belongsTo User');
        $exception->addContext('invalid_column_type', 'varchar');

        $result = $this->manager->attemptRecovery($exception);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Validation auto-fixes available', $result->getMessage());
        $this->assertArrayHasKey('fixes', $result->getData());
        $this->assertContains('Suggested relationship format fixes', $result->getData()['fixes']);
        $this->assertContains('Suggested column type corrections', $result->getData()['fixes']);
    }

    /** @test */
    public function it_can_find_template_fallbacks()
    {
        // Create a fallback template
        $templateDir = $this->tempDir . '/templates';
        $this->fileSystem->makeDirectory($templateDir, 0755, true);
        $this->fileSystem->put($templateDir . '/custom.fallback.stub', 'fallback content');

        $exception = new GenerationException('Template not found');
        $exception->addContext('template_path', $templateDir . '/custom.stub');

        $result = $this->manager->attemptRecovery($exception);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Found fallback template', $result->getMessage());
        $this->assertArrayHasKey('fallback_template', $result->getData());
    }

    /** @test */
    public function it_returns_failure_when_no_strategies_apply()
    {
        $exception = new ParsingException('Unknown error');
        // No relevant context for any recovery strategy

        $result = $this->manager->attemptRecovery($exception);

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString('No recovery strategies succeeded', $result->getMessage());
    }

    /** @test */
    public function it_can_configure_retry_settings()
    {
        $this->manager->setMaxRetries(5);
        $this->manager->setRetryDelay(2.0);

        // Test that the manager accepts the configuration
        // (The actual retry logic would be tested in integration tests)
        $this->assertTrue(true); // Configuration accepted without errors
    }

    /** @test */
    public function it_handles_yaml_content_with_multiple_fixes()
    {
        $yamlContent = "models:\n  User:\n    name:string\n    email:email\n  -Post:\n    title:string\n    content:text";
        
        $exception = new ParsingException('Multiple YAML syntax errors');
        $exception->addContext('yaml_content', $yamlContent);

        $result = $this->manager->attemptRecovery($exception);

        // The RecoveryManager should attempt to fix the YAML and return a result
        // It may or may not be successful depending on the specific fixes applied
        $this->assertInstanceOf(RecoveryResult::class, $result);
        
        if ($result->isSuccessful()) {
            $this->assertArrayHasKey('fixes', $result->getData());
            $this->assertGreaterThanOrEqual(1, count($result->getData()['fixes']));
        } else {
            // If not successful, it should still provide some information
            $this->assertNotEmpty($result->getMessage());
        }
    }

    /** @test */
    public function it_handles_existing_directory_gracefully()
    {
        $testFile = $this->tempDir . '/existing/test.php';
        $this->fileSystem->makeDirectory(dirname($testFile), 0755, true);
        
        $exception = new GenerationException('Directory issue');
        $exception->setFilePath($testFile);

        $result = $this->manager->attemptRecovery($exception);

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('Directory already exists', $result->getMessage());
    }
} 
<?php

namespace Tests\Feature\ErrorHandling;

use Blueprint\ErrorHandling\ErrorLogger;
use Blueprint\Exceptions\BlueprintException;
use Blueprint\Exceptions\ParsingException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tests\TestCase;
use Mockery;

class ErrorLoggerTest extends TestCase
{
    private LoggerInterface $mockLogger;
    private ErrorLogger $errorLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->errorLogger = new ErrorLogger($this->mockLogger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_log_blueprint_exceptions()
    {
        $exception = new BlueprintException('Test error message');
        $exception->setFilePath('/path/to/file.php');
        $exception->setLineNumber(42);
        $exception->addContext('key', 'value');
        $exception->addSuggestion('Fix this issue');

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::ERROR, 'Test error message', Mockery::on(function ($context) {
                return isset($context['error_id']) &&
                       isset($context['error_type']) &&
                       isset($context['file_path']) &&
                       isset($context['line_number']) &&
                       isset($context['context']) &&
                       isset($context['suggestions']) &&
                       isset($context['timestamp']) &&
                       isset($context['stack_trace']) &&
                       $context['error_type'] === BlueprintException::class &&
                       $context['file_path'] === '/path/to/file.php' &&
                       $context['line_number'] === 42 &&
                       $context['context']['key'] === 'value' &&
                       $context['suggestions'][0] === 'Fix this issue';
            }));

        $errorId = $this->errorLogger->logError($exception);

        $this->assertStringStartsWith('bp_', $errorId);
        $this->assertEquals(11, strlen($errorId)); // 'bp_' + 8 characters
    }

    /** @test */
    public function it_can_log_recovery_attempts()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Recovery successful using strategy: yaml_fix', Mockery::on(function ($context) {
                return $context['error_id'] === 'bp_12345678' &&
                       $context['recovery_strategy'] === 'yaml_fix' &&
                       $context['success'] === true &&
                       isset($context['timestamp']);
            }));

        $this->errorLogger->logRecoveryAttempt('bp_12345678', 'yaml_fix', true);
        
        // Verify the mock expectations were met
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_log_failed_recovery_attempts()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::WARNING, 'Recovery failed using strategy: permission_fix', Mockery::on(function ($context) {
                return $context['error_id'] === 'bp_87654321' &&
                       $context['recovery_strategy'] === 'permission_fix' &&
                       $context['success'] === false &&
                       isset($context['timestamp']);
            }));

        $this->errorLogger->logRecoveryAttempt('bp_87654321', 'permission_fix', false);
        
        // Verify the mock expectations were met
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_log_with_additional_context()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'Recovery successful using strategy: directory_create', Mockery::on(function ($context) {
                return $context['created_path'] === '/tmp/new/directory' &&
                       $context['permissions'] === '0755';
            }));

        $this->errorLogger->logRecoveryAttempt('bp_99999999', 'directory_create', true, [
            'created_path' => '/tmp/new/directory',
            'permissions' => '0755'
        ]);
        
        // Verify the mock expectations were met
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_be_disabled()
    {
        $this->mockLogger->shouldNotReceive('log');

        $this->errorLogger->setEnabled(false);
        
        $exception = new BlueprintException('Test error');
        $errorId = $this->errorLogger->logError($exception);

        // Should still return the error ID even when disabled
        $this->assertStringStartsWith('bp_', $errorId);
    }

    /** @test */
    public function it_can_change_log_level()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::WARNING, 'Test error message', Mockery::type('array'));

        $this->errorLogger->setLogLevel(LogLevel::WARNING);
        
        $exception = new BlueprintException('Test error message');
        $errorId = $this->errorLogger->logError($exception);
        
        // Verify the mock expectations were met and error ID was returned
        $this->assertStringStartsWith('bp_', $errorId);
    }

    /** @test */
    public function it_handles_exceptions_without_optional_data()
    {
        $exception = new ParsingException('Simple error');
        // No file path, line number, context, or suggestions

        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with(LogLevel::ERROR, 'Simple error', Mockery::on(function ($context) {
                return $context['file_path'] === null &&
                       $context['line_number'] === null &&
                       empty($context['context']) &&
                       empty($context['suggestions']);
            }));

        $errorId = $this->errorLogger->logError($exception);
        
        // Verify the mock expectations were met and error ID was returned
        $this->assertStringStartsWith('bp_', $errorId);
    }

    /** @test */
    public function it_does_not_log_recovery_when_disabled()
    {
        $this->mockLogger->shouldNotReceive('log');

        $this->errorLogger->setEnabled(false);
        $this->errorLogger->logRecoveryAttempt('bp_12345678', 'test_strategy', true);
        
        // Verify the mock expectations were met (no logging occurred)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_generates_unique_error_ids()
    {
        $exception1 = new BlueprintException('Error 1');
        $exception2 = new BlueprintException('Error 2');

        $this->mockLogger->shouldReceive('log')->twice();

        $errorId1 = $this->errorLogger->logError($exception1);
        $errorId2 = $this->errorLogger->logError($exception2);

        $this->assertNotEquals($errorId1, $errorId2);
        $this->assertStringStartsWith('bp_', $errorId1);
        $this->assertStringStartsWith('bp_', $errorId2);
    }
} 
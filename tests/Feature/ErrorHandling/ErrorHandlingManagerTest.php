<?php

namespace Tests\Feature\ErrorHandling;

use Blueprint\ErrorHandling\ErrorHandlingManager;
use Blueprint\ErrorHandling\ErrorLogger;
use Blueprint\ErrorHandling\RecoveryManager;
use Blueprint\Exceptions\BlueprintException;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Exceptions\GenerationException;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Mockery;

class ErrorHandlingManagerTest extends TestCase
{
    private ErrorHandlingManager $manager;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->manager = new ErrorHandlingManager($this->mockLogger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_handle_blueprint_exceptions_with_logging()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('error', 'Test error message', Mockery::type('array'));

        $exception = new BlueprintException('Test error message');
        $result = $this->manager->handleException($exception);

        $this->assertNotNull($result);
        $this->assertEquals($exception, $result->getException());
        $this->assertStringStartsWith('bp_', $result->getErrorId());
        $this->assertEquals(8, strlen(str_replace('bp_', '', $result->getErrorId())));
    }

    /** @test */
    public function it_can_handle_exceptions_with_recovery_attempts()
    {
        $this->mockLogger->shouldReceive('log')->atLeast()->twice(); // Error log + recovery logs

        $exception = new GenerationException('File generation failed');
        $exception->addContext('permission_error', true);
        $exception->setFilePath('/tmp/test.php');

        $result = $this->manager->handleException($exception, true);

        $this->assertTrue($result->hasRecoveryResult());
        $this->assertNotNull($result->getRecoveryResult());
    }

    /** @test */
    public function it_can_disable_auto_recovery()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('error', 'Test error', Mockery::type('array'));

        $this->manager->setAutoRecoveryEnabled(false);
        
        $exception = new BlueprintException('Test error');
        $result = $this->manager->handleException($exception);

        $this->assertFalse($result->hasRecoveryResult());
        $this->assertFalse($this->manager->isAutoRecoveryEnabled());
    }

    /** @test */
    public function it_can_create_and_handle_new_exceptions()
    {
        $this->mockLogger->shouldReceive('log')
            ->once()
            ->with('error', 'New error message', Mockery::type('array'));

        $result = $this->manager->createAndHandleException(
            'New error message',
            500,
            null,
            ['key' => 'value'],
            ['Fix this issue']
        );

        $this->assertEquals('New error message', $result->getException()->getMessage());
        $this->assertEquals(500, $result->getException()->getCode());
        $this->assertEquals(['key' => 'value'], $result->getException()->getContext());
        $this->assertEquals(['Fix this issue'], $result->getException()->getSuggestions());
    }

    /** @test */
    public function it_provides_access_to_error_logger()
    {
        $errorLogger = $this->manager->getErrorLogger();
        $this->assertInstanceOf(ErrorLogger::class, $errorLogger);
    }

    /** @test */
    public function it_provides_access_to_recovery_manager()
    {
        $recoveryManager = $this->manager->getRecoveryManager();
        $this->assertInstanceOf(RecoveryManager::class, $recoveryManager);
    }
} 
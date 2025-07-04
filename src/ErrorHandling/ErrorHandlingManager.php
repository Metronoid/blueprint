<?php

namespace Blueprint\ErrorHandling;

use Blueprint\Exceptions\BlueprintException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ErrorHandlingManager
{
    private ErrorLogger $errorLogger;
    private RecoveryManager $recoveryManager;
    private bool $autoRecoveryEnabled = true;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->errorLogger = new ErrorLogger($logger ?? new NullLogger());
        $this->recoveryManager = new RecoveryManager($this->errorLogger);
    }

    /**
     * Handle a Blueprint exception with logging and optional recovery.
     */
    public function handleException(BlueprintException $exception, bool $attemptRecovery = true): ErrorHandlingResult
    {
        // Log the error
        $errorId = $this->errorLogger->logError($exception);

        $recoveryResult = null;
        if ($attemptRecovery && $this->autoRecoveryEnabled) {
            $recoveryResult = $this->recoveryManager->attemptRecovery($exception);
        }

        return new ErrorHandlingResult($exception, $errorId, $recoveryResult);
    }

    /**
     * Create and handle a new BlueprintException.
     */
    public function createAndHandleException(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
        array $suggestions = [],
        bool $attemptRecovery = true
    ): ErrorHandlingResult {
        $exception = new BlueprintException($message, $code, $previous, $context, $suggestions);
        return $this->handleException($exception, $attemptRecovery);
    }

    /**
     * Get the error logger instance.
     */
    public function getErrorLogger(): ErrorLogger
    {
        return $this->errorLogger;
    }

    /**
     * Get the recovery manager instance.
     */
    public function getRecoveryManager(): RecoveryManager
    {
        return $this->recoveryManager;
    }

    /**
     * Enable or disable automatic recovery attempts.
     */
    public function setAutoRecoveryEnabled(bool $enabled): void
    {
        $this->autoRecoveryEnabled = $enabled;
    }

    /**
     * Check if auto-recovery is enabled.
     */
    public function isAutoRecoveryEnabled(): bool
    {
        return $this->autoRecoveryEnabled;
    }
} 
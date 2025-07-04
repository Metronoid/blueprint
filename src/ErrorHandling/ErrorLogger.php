<?php

namespace Blueprint\ErrorHandling;

use Blueprint\Exceptions\BlueprintException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

class ErrorLogger
{
    private LoggerInterface $logger;
    private string $logLevel;
    private bool $enabled;

    public function __construct(LoggerInterface $logger = null, string $logLevel = LogLevel::ERROR, bool $enabled = true)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->logLevel = $logLevel;
        $this->enabled = $enabled;
    }

    public function logError(BlueprintException $exception): string
    {
        if (!$this->enabled) {
            return $exception->getErrorId();
        }

        $errorId = $exception->getErrorId();
        $context = $this->buildLogContext($exception);

        $this->logger->log($this->logLevel, $exception->getMessage(), $context);

        return $errorId;
    }

    public function logRecoveryAttempt(string $errorId, string $strategy, bool $success, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $logContext = [
            'error_id' => $errorId,
            'recovery_strategy' => $strategy,
            'success' => $success,
            'timestamp' => date('c'),
        ] + $context;

        $level = $success ? LogLevel::INFO : LogLevel::WARNING;
        $message = $success 
            ? "Recovery successful using strategy: {$strategy}"
            : "Recovery failed using strategy: {$strategy}";

        $this->logger->log($level, $message, $logContext);
    }

    private function buildLogContext(BlueprintException $exception): array
    {
        return [
            'error_id' => $exception->getErrorId(),
            'error_type' => get_class($exception),
            'file_path' => $exception->getFilePath(),
            'line_number' => $exception->getLineNumber(),
            'context' => $exception->getContext(),
            'suggestions' => $exception->getSuggestions(),
            'timestamp' => date('c'),
            'stack_trace' => $exception->getTraceAsString(),
        ];
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setLogLevel(string $logLevel): void
    {
        $this->logLevel = $logLevel;
    }
} 
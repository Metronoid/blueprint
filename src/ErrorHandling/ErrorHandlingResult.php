<?php

namespace Blueprint\ErrorHandling;

use Blueprint\Exceptions\BlueprintException;

class ErrorHandlingResult
{
    private BlueprintException $exception;
    private string $errorId;
    private ?RecoveryResult $recoveryResult;

    public function __construct(
        BlueprintException $exception,
        string $errorId,
        ?RecoveryResult $recoveryResult = null
    ) {
        $this->exception = $exception;
        $this->errorId = $errorId;
        $this->recoveryResult = $recoveryResult;
    }

    /**
     * Get the original exception.
     */
    public function getException(): BlueprintException
    {
        return $this->exception;
    }

    /**
     * Get the unique error ID.
     */
    public function getErrorId(): string
    {
        return $this->errorId;
    }

    /**
     * Get the recovery result, if recovery was attempted.
     */
    public function getRecoveryResult(): ?RecoveryResult
    {
        return $this->recoveryResult;
    }

    /**
     * Check if recovery was attempted.
     */
    public function hasRecoveryResult(): bool
    {
        return $this->recoveryResult !== null;
    }

    /**
     * Check if recovery was successful.
     */
    public function isRecoverySuccessful(): bool
    {
        return $this->recoveryResult !== null && $this->recoveryResult->isSuccessful();
    }

    /**
     * Get a formatted message including recovery information.
     */
    public function getFormattedMessage(): string
    {
        $message = $this->exception->getFormattedMessage();
        $message .= "\n\nError ID: {$this->errorId}";

        if ($this->recoveryResult !== null) {
            $message .= "\n\nRecovery Attempt: ";
            if ($this->recoveryResult->isSuccessful()) {
                $message .= "✅ " . $this->recoveryResult->getMessage();
                if ($this->recoveryResult->hasData('suggestion')) {
                    $message .= "\n  Suggestion: " . $this->recoveryResult->getDataValue('suggestion');
                }
            } else {
                $message .= "❌ " . $this->recoveryResult->getMessage();
            }
        }

        return $message;
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'error_id' => $this->errorId,
            'exception' => [
                'message' => $this->exception->getMessage(),
                'code' => $this->exception->getCode(),
                'file_path' => $this->exception->getFilePath(),
                'line_number' => $this->exception->getLineNumber(),
                'context' => $this->exception->getContext(),
                'suggestions' => $this->exception->getSuggestions(),
            ],
            'recovery' => $this->recoveryResult?->toArray(),
        ];
    }
} 
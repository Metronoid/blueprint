<?php

namespace Blueprint\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception class for Blueprint-specific errors.
 * 
 * Provides enhanced error reporting with context and suggestions for common issues.
 */
class BlueprintException extends Exception
{
    protected array $context = [];
    protected array $suggestions = [];
    protected ?string $filePath = null;
    protected ?int $lineNumber = null;
    protected string $errorId;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        array $suggestions = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->suggestions = $suggestions;
        $this->errorId = $this->generateErrorId();
    }

    /**
     * Set the file path where the error occurred.
     */
    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * Set the line number where the error occurred.
     */
    public function setLineNumber(int $lineNumber): self
    {
        $this->lineNumber = $lineNumber;
        return $this;
    }

    /**
     * Add context information to the exception.
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Add a suggestion for fixing the error.
     */
    public function addSuggestion(string $suggestion): self
    {
        $this->suggestions[] = $suggestion;
        return $this;
    }

    /**
     * Get the context information.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get the suggestions for fixing the error.
     */
    public function getSuggestions(): array
    {
        return $this->suggestions;
    }

    /**
     * Get the file path where the error occurred.
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Get the line number where the error occurred.
     */
    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    /**
     * Get the unique error ID for this exception.
     */
    public function getErrorId(): string
    {
        return $this->errorId;
    }

    /**
     * Get a formatted error message with context and suggestions.
     */
    public function getFormattedMessage(): string
    {
        $message = $this->getMessage();

        if ($this->filePath) {
            $location = $this->filePath;
            if ($this->lineNumber) {
                $location .= " on line {$this->lineNumber}";
            }
            $message = "{$message}\n\nFile: {$location}";
        }

        if (!empty($this->context)) {
            $message .= "\n\nContext:";
            foreach ($this->context as $key => $value) {
                $message .= "\n  {$key}: " . $this->formatContextValue($value);
            }
        }

        if (!empty($this->suggestions)) {
            $message .= "\n\nSuggestions:";
            foreach ($this->suggestions as $suggestion) {
                $message .= "\n  • {$suggestion}";
            }
        }

        return $message;
    }

    /**
     * Format a context value for display.
     */
    protected function formatContextValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        return (string) $value;
    }

    /**
     * Generate a unique error ID for tracking purposes.
     */
    protected function generateErrorId(): string
    {
        return 'bp_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }
} 
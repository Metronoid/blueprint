<?php

namespace Blueprint\ErrorHandling;

class RecoveryResult
{
    private bool $successful;
    private string $message;
    private array $data;

    public function __construct(bool $successful, string $message, array $data = [])
    {
        $this->successful = $successful;
        $this->message = $message;
        $this->data = $data;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getDataValue(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function hasData(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
} 
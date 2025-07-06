<?php

namespace BlueprintExtensions\Auditing\Exceptions;

use Exception;

class RewindException extends Exception
{
    /**
     * Create a new rewind exception instance.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message = "", int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
} 
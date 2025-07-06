<?php

namespace BlueprintExtensions\Auditing\Exceptions;

use Exception;

class MergeConflictException extends Exception
{
    /**
     * The merge conflicts.
     *
     * @var array
     */
    protected $conflicts;

    /**
     * Create a new merge conflict exception instance.
     *
     * @param string $message The exception message
     * @param array $conflicts The merge conflicts
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message = "", array $conflicts = [], int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->conflicts = $conflicts;
    }

    /**
     * Get the merge conflicts.
     *
     * @return array
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
} 
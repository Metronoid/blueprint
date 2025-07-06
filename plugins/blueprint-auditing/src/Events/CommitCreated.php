<?php

namespace BlueprintExtensions\Auditing\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommitCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The model that the commit was created for.
     *
     * @var Model
     */
    public $model;

    /**
     * The commit ID.
     *
     * @var string
     */
    public $commitId;

    /**
     * The branch ID.
     *
     * @var string
     */
    public $branchId;

    /**
     * The commit message.
     *
     * @var string
     */
    public $message;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that the commit was created for
     * @param string $commitId The commit ID
     * @param string $branchId The branch ID
     * @param string $message The commit message
     */
    public function __construct(Model $model, string $commitId, string $branchId, string $message)
    {
        $this->model = $model;
        $this->commitId = $commitId;
        $this->branchId = $branchId;
        $this->message = $message;
    }
} 
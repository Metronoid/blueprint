<?php

namespace BlueprintExtensions\Auditing\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The model that the branch was created for.
     *
     * @var Model
     */
    public $model;

    /**
     * The branch ID.
     *
     * @var string
     */
    public $branchId;

    /**
     * The branch name.
     *
     * @var string
     */
    public $branchName;

    /**
     * The parent branch ID.
     *
     * @var string
     */
    public $parentBranchId;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that the branch was created for
     * @param string $branchId The branch ID
     * @param string $branchName The branch name
     * @param string $parentBranchId The parent branch ID
     */
    public function __construct(Model $model, string $branchId, string $branchName, string $parentBranchId)
    {
        $this->model = $model;
        $this->branchId = $branchId;
        $this->branchName = $branchName;
        $this->parentBranchId = $parentBranchId;
    }
} 
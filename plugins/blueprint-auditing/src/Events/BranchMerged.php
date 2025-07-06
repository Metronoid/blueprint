<?php

namespace BlueprintExtensions\Auditing\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchMerged
{
    use Dispatchable, SerializesModels;

    /**
     * The model that the branches were merged for.
     *
     * @var Model
     */
    public $model;

    /**
     * The target branch ID.
     *
     * @var string
     */
    public $targetBranchId;

    /**
     * The source branch ID.
     *
     * @var string
     */
    public $sourceBranchId;

    /**
     * The merge commit ID.
     *
     * @var string
     */
    public $mergeCommitId;

    /**
     * The merge strategy used.
     *
     * @var string
     */
    public $strategy;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that the branches were merged for
     * @param string $targetBranchId The target branch ID
     * @param string $sourceBranchId The source branch ID
     * @param string $mergeCommitId The merge commit ID
     * @param string $strategy The merge strategy used
     */
    public function __construct(Model $model, string $targetBranchId, string $sourceBranchId, string $mergeCommitId, string $strategy)
    {
        $this->model = $model;
        $this->targetBranchId = $targetBranchId;
        $this->sourceBranchId = $sourceBranchId;
        $this->mergeCommitId = $mergeCommitId;
        $this->strategy = $strategy;
    }
} 
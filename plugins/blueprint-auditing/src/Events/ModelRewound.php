<?php

namespace BlueprintExtensions\Auditing\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use OwenIt\Auditing\Models\Audit;

class ModelRewound
{
    use Dispatchable, SerializesModels;

    /**
     * The model that was rewound.
     *
     * @var Model
     */
    public $model;

    /**
     * The audit that the model was rewound to.
     *
     * @var Audit
     */
    public $audit;

    /**
     * Create a new event instance.
     *
     * @param Model $model The model that was rewound
     * @param Audit $audit The audit that the model was rewound to
     */
    public function __construct(Model $model, Audit $audit)
    {
        $this->model = $model;
        $this->audit = $audit;
    }
} 
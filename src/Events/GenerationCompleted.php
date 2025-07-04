<?php

namespace Blueprint\Events;

use Blueprint\Tree;

class GenerationCompleted extends GenerationEvent
{
    public function __construct(
        Tree $tree,
        public readonly array $generated,
        array $only = [],
        array $skip = []
    ) {
        parent::__construct($tree, $only, $skip);
    }
} 
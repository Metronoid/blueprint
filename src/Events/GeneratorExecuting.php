<?php

namespace Blueprint\Events;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;

class GeneratorExecuting extends GenerationEvent
{
    public function __construct(
        Tree $tree,
        public readonly Generator $generator,
        array $only = [],
        array $skip = []
    ) {
        parent::__construct($tree, $only, $skip);
    }
} 
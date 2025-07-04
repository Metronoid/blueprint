<?php

namespace Blueprint\Events;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;

class GeneratorExecuted extends GenerationEvent
{
    public function __construct(
        Tree $tree,
        public readonly Generator $generator,
        public readonly array $output,
        array $only = [],
        array $skip = []
    ) {
        parent::__construct($tree, $only, $skip);
    }
} 
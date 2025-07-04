<?php

namespace Blueprint\Events;

use Blueprint\Tree;

abstract class GenerationEvent
{
    public function __construct(
        public readonly Tree $tree,
        public readonly array $only = [],
        public readonly array $skip = []
    ) {}
} 
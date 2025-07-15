<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ServiceProviderGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['providers'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        return $this->output;
    }
} 
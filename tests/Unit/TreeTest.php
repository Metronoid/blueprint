<?php

namespace Tests\Unit;

use Blueprint\Tree;
use Blueprint\Exceptions\GenerationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \Blueprint\Tree
 */
final class TreeTest extends TestCase
{
    #[Test]
    public function it_throws_when_a_referenced_model_cannot_be_found(): void
    {
        $this->expectException(GenerationException::class);
        $this->expectExceptionMessage("Model 'App\Models\Unknown' not found in context 'Unknown'");

        $tree = new Tree(['models' => []]);
        $tree->modelForContext('Unknown', true);
    }
}

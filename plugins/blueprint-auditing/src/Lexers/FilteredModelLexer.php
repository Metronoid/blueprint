<?php

namespace BlueprintExtensions\Auditing\Lexers;

use Blueprint\Lexers\ModelLexer;

class FilteredModelLexer extends ModelLexer
{
    /**
     * Analyze tokens and filter out auditing configurations before processing.
     *
     * @param array $tokens The parsed YAML tokens
     * @return array The tree with models
     */
    public function analyze(array $tokens): array
    {
        // Filter out auditing configurations from model definitions
        if (isset($tokens['models'])) {
            foreach ($tokens['models'] as $name => $definition) {
                if (isset($definition['auditing'])) {
                    unset($tokens['models'][$name]['auditing']);
                }
            }
        }

        // Call the parent ModelLexer with filtered tokens
        return parent::analyze($tokens);
    }
} 
<?php

namespace Blueprint\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Component;
use Blueprint\Models\Controller;
use Blueprint\Models\Statements\FrontendStatement;

class FrontendLexer implements Lexer
{
    public function analyze(array $tokens): array
    {
        $registry = [
            'frontend' => [],
        ];

        if (!isset($tokens['frontend'])) {
            return $registry;
        }

        foreach ($tokens['frontend'] as $name => $definition) {
            $registry['frontend'][] = $this->analyzeFrontendDefinition($name, $definition);
        }

        return $registry;
    }

    protected function analyzeFrontendDefinition(string $name, array $definition): Component
    {
        $component = new Component($name);

        if (isset($definition['framework'])) {
            $component->setFramework($definition['framework']);
        }

        if (isset($definition['type'])) {
            $component->setType($definition['type']);
        }

        if (isset($definition['props'])) {
            $component->setProps($definition['props']);
        }

        if (isset($definition['state'])) {
            $component->setState($definition['state']);
        }

        if (isset($definition['methods'])) {
            $component->setMethods($definition['methods']);
        }

        if (isset($definition['styles'])) {
            $component->setStyles($definition['styles']);
        }

        if (isset($definition['dependencies'])) {
            $component->setDependencies($definition['dependencies']);
        }

        if (isset($definition['layout'])) {
            $component->setLayout($definition['layout']);
        }

        if (isset($definition['route'])) {
            $component->setRoute($definition['route']);
        }

        if (isset($definition['api'])) {
            $component->setApi($definition['api']);
        }

        return $component;
    }
} 
<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;

class CompositeGenerator implements Generator
{
    protected Filesystem $filesystem;
    protected array $generators = [];
    protected array $types = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * Add a generator to the composite.
     */
    public function addGenerator(Generator $generator): self
    {
        $this->generators[] = $generator;
        
        // Merge types from all generators
        $this->types = array_unique(array_merge($this->types, $generator->types()));
        
        // Sort by priority if it's a plugin generator
        if ($generator instanceof PluginGenerator) {
            $this->sortGeneratorsByPriority();
        }
        
        return $this;
    }

    /**
     * Remove a generator from the composite.
     */
    public function removeGenerator(Generator $generator): self
    {
        $this->generators = array_filter($this->generators, function ($g) use ($generator) {
            return $g !== $generator;
        });
        
        // Recalculate types
        $this->recalculateTypes();
        
        return $this;
    }

    /**
     * Get all generators.
     */
    public function getGenerators(): array
    {
        return $this->generators;
    }

    /**
     * Get generators by type.
     */
    public function getGeneratorsByType(string $type): array
    {
        return array_filter($this->generators, function (Generator $generator) use ($type) {
            return in_array($type, $generator->types());
        });
    }

    /**
     * Get plugin generators only.
     */
    public function getPluginGenerators(): array
    {
        return array_filter($this->generators, function (Generator $generator) {
            return $generator instanceof PluginGenerator;
        });
    }

    /**
     * Run all generators and combine their output.
     */
    public function output(Tree $tree): array
    {
        $combinedOutput = [];
        
        foreach ($this->generators as $generator) {
            // Check if plugin generator should run
            if ($generator instanceof PluginGenerator && !$generator->shouldRun($tree)) {
                continue;
            }
            
            try {
                $output = $generator->output($tree);
                
                // Merge outputs
                foreach ($output as $key => $files) {
                    if (!isset($combinedOutput[$key])) {
                        $combinedOutput[$key] = [];
                    }
                    
                    if (is_array($files)) {
                        $combinedOutput[$key] = array_merge($combinedOutput[$key], $files);
                    } else {
                        $combinedOutput[$key][] = $files;
                    }
                }
            } catch (\Exception $e) {
                // Log error but continue with other generators
                if (function_exists('logger')) {
                    logger()->error("Generator " . get_class($generator) . " failed: " . $e->getMessage());
                }
            }
        }
        
        return $combinedOutput;
    }

    /**
     * Get all types handled by composite generators.
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * Sort generators by priority (plugin generators only).
     */
    protected function sortGeneratorsByPriority(): void
    {
        usort($this->generators, function (Generator $a, Generator $b) {
            $priorityA = $a instanceof PluginGenerator ? $a->getPriority() : 0;
            $priorityB = $b instanceof PluginGenerator ? $b->getPriority() : 0;
            
            // Higher priority first
            return $priorityB <=> $priorityA;
        });
    }

    /**
     * Recalculate types from all generators.
     */
    protected function recalculateTypes(): void
    {
        $this->types = [];
        
        foreach ($this->generators as $generator) {
            $this->types = array_unique(array_merge($this->types, $generator->types()));
        }
    }

    /**
     * Get generator statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total_generators' => count($this->generators),
            'plugin_generators' => count($this->getPluginGenerators()),
            'core_generators' => count($this->generators) - count($this->getPluginGenerators()),
            'types_handled' => count($this->types),
            'types' => $this->types,
        ];
        
        // Group by type
        $byType = [];
        foreach ($this->types as $type) {
            $byType[$type] = count($this->getGeneratorsByType($type));
        }
        $stats['generators_by_type'] = $byType;
        
        return $stats;
    }
} 
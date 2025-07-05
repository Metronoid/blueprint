<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;

class ExtendableGenerator extends AbstractPluginGenerator
{
    protected Generator $baseGenerator;
    protected array $extensions = [];

    public function __construct(Filesystem $files, Plugin $plugin = null)
    {
        parent::__construct($files, $plugin);
    }

    /**
     * Create an extendable generator wrapping a base generator.
     */
    public static function wrap(Generator $baseGenerator, Plugin $plugin, Filesystem $files): self
    {
        $instance = new self($files, $plugin);
        $instance->baseGenerator = $baseGenerator;
        $instance->types = $baseGenerator->types();
        return $instance;
    }

    /**
     * Add an extension to this generator.
     */
    public function addExtension(callable $extension): self
    {
        $this->extensions[] = $extension;
        return $this;
    }

    /**
     * Add multiple extensions.
     */
    public function addExtensions(array $extensions): self
    {
        foreach ($extensions as $extension) {
            $this->addExtension($extension);
        }
        return $this;
    }

    /**
     * Get the base generator.
     */
    public function getBaseGenerator(): Generator
    {
        if (!isset($this->baseGenerator)) {
            throw new \RuntimeException('Base generator not set. Use ExtendableGenerator::wrap() to create instance.');
        }
        return $this->baseGenerator;
    }

    /**
     * Execute the base generator and apply extensions.
     */
    public function output(Tree $tree): array
    {
        if (!isset($this->baseGenerator)) {
            throw new \RuntimeException('Base generator not set. Use ExtendableGenerator::wrap() to create instance.');
        }

        // Get base output
        $baseOutput = $this->baseGenerator->output($tree);
        
        // Apply extensions
        $extendedOutput = $baseOutput;
        foreach ($this->extensions as $extension) {
            $extendedOutput = $extension($extendedOutput, $tree, $this);
        }
        
        return $extendedOutput;
    }

    /**
     * Get the types handled by the base generator.
     */
    public function types(): array
    {
        if (!isset($this->baseGenerator)) {
            return $this->types;
        }
        return $this->baseGenerator->types();
    }

    /**
     * Get the name of this extendable generator.
     */
    public function getName(): string
    {
        return 'Extended' . class_basename($this->baseGenerator);
    }

    /**
     * Get the description.
     */
    public function getDescription(): string
    {
        return 'Extended version of ' . class_basename($this->baseGenerator) . ' with ' . count($this->extensions) . ' extensions';
    }

    /**
     * Check if this generator should run.
     */
    public function shouldRun(Tree $tree): bool
    {
        // Run if base generator would run and we have extensions
        return !empty($this->extensions) && parent::shouldRun($tree);
    }

    /**
     * Get extension statistics.
     */
    public function getExtensionStats(): array
    {
        return [
            'base_generator' => get_class($this->baseGenerator),
            'extensions_count' => count($this->extensions),
            'types_handled' => $this->types(),
        ];
    }
} 
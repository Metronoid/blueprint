<?php

namespace Blueprint\Plugin;

use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Plugin;
use Blueprint\Contracts\PluginGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

class GeneratorRegistry
{
    protected Filesystem $filesystem;
    protected array $generators = [];
    protected array $pluginGenerators = [];
    protected array $typeMap = [];
    protected CompositeGenerator $compositeGenerator;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->compositeGenerator = new CompositeGenerator($filesystem);
    }

    /**
     * Register a core generator.
     */
    public function registerGenerator(string $type, Generator $generator): self
    {
        $msg = '[GeneratorRegistry] Registering generator for type: ' . $type . ' (' . get_class($generator) . ')';
        file_put_contents('/tmp/genreg.log', $msg . "\n", FILE_APPEND);
        $this->generators[$type] = $generator;
        $this->updateTypeMap($type, $generator);
        $this->compositeGenerator->addGenerator($generator);
        
        return $this;
    }

    /**
     * Register a plugin generator.
     */
    public function registerPluginGenerator(PluginGenerator $generator): self
    {
        $pluginName = $generator->getPlugin()->getName();
        $generatorName = $generator->getName();
        
        if (!isset($this->pluginGenerators[$pluginName])) {
            $this->pluginGenerators[$pluginName] = [];
        }
        
        $this->pluginGenerators[$pluginName][$generatorName] = $generator;
        
        // Add to composite generator
        $this->compositeGenerator->addGenerator($generator);
        
        // Update type mappings
        foreach ($generator->types() as $type) {
            $this->updateTypeMap($type, $generator);
        }
        
        return $this;
    }

    /**
     * Register multiple generators from a plugin.
     */
    public function registerPluginGenerators(Plugin $plugin, array $generators): self
    {
        foreach ($generators as $generator) {
            if ($generator instanceof PluginGenerator) {
                $this->registerPluginGenerator($generator);
            }
        }
        
        return $this;
    }

    /**
     * Unregister a plugin generator.
     */
    public function unregisterPluginGenerator(string $pluginName, string $generatorName): self
    {
        if (isset($this->pluginGenerators[$pluginName][$generatorName])) {
            $generator = $this->pluginGenerators[$pluginName][$generatorName];
            
            // Remove from composite
            $this->compositeGenerator->removeGenerator($generator);
            
            // Remove from registry
            unset($this->pluginGenerators[$pluginName][$generatorName]);
            
            // Clean up empty plugin entry
            if (empty($this->pluginGenerators[$pluginName])) {
                unset($this->pluginGenerators[$pluginName]);
            }
            
            // Update type mappings
            $this->rebuildTypeMap();
        }
        
        return $this;
    }

    /**
     * Unregister all generators from a plugin.
     */
    public function unregisterPlugin(string $pluginName): self
    {
        if (isset($this->pluginGenerators[$pluginName])) {
            foreach ($this->pluginGenerators[$pluginName] as $generator) {
                $this->compositeGenerator->removeGenerator($generator);
            }
            
            unset($this->pluginGenerators[$pluginName]);
            $this->rebuildTypeMap();
        }
        
        return $this;
    }

    /**
     * Get all generators for a specific type.
     */
    public function getGeneratorsForType(string $type): array
    {
        return $this->typeMap[$type] ?? [];
    }

    /**
     * Get the core generator for a type.
     */
    public function getCoreGenerator(string $type): ?Generator
    {
        return $this->generators[$type] ?? null;
    }

    /**
     * Get plugin generators for a type.
     */
    public function getPluginGeneratorsForType(string $type): array
    {
        $generators = [];
        
        foreach ($this->pluginGenerators as $pluginGenerators) {
            foreach ($pluginGenerators as $generator) {
                if ($generator->canHandle($type)) {
                    $generators[] = $generator;
                }
            }
        }
        
        // Sort by priority
        usort($generators, function (PluginGenerator $a, PluginGenerator $b) {
            return $b->getPriority() <=> $a->getPriority();
        });
        
        return $generators;
    }

    /**
     * Get all plugin generators.
     */
    public function getPluginGenerators(): array
    {
        $generators = [];
        
        foreach ($this->pluginGenerators as $pluginGenerators) {
            $generators = array_merge($generators, array_values($pluginGenerators));
        }
        
        return $generators;
    }

    /**
     * Get generators by plugin name.
     */
    public function getGeneratorsByPlugin(string $pluginName): array
    {
        return $this->pluginGenerators[$pluginName] ?? [];
    }

    /**
     * Get the composite generator with all registered generators.
     */
    public function getCompositeGenerator(): CompositeGenerator
    {
        return $this->compositeGenerator;
    }

    /**
     * Generate output using all registered generators.
     */
    public function generate(Tree $tree): array
    {
        return $this->compositeGenerator->output($tree);
    }

    /**
     * Get all registered types.
     */
    public function getTypes(): array
    {
        return array_keys($this->typeMap);
    }

    /**
     * Check if a type is supported.
     */
    public function supportsType(string $type): bool
    {
        return isset($this->typeMap[$type]);
    }

    /**
     * Get registry statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'core_generators' => count($this->generators),
            'plugin_generators' => count($this->getPluginGenerators()),
            'total_generators' => count($this->generators) + count($this->getPluginGenerators()),
            'plugins_with_generators' => count($this->pluginGenerators),
            'supported_types' => count($this->typeMap),
            'types' => array_keys($this->typeMap),
        ];
        
        // Add plugin breakdown
        $pluginBreakdown = [];
        foreach ($this->pluginGenerators as $pluginName => $generators) {
            $pluginBreakdown[$pluginName] = count($generators);
        }
        $stats['generators_by_plugin'] = $pluginBreakdown;
        
        // Add type breakdown
        $typeBreakdown = [];
        foreach ($this->typeMap as $type => $generators) {
            $typeBreakdown[$type] = count($generators);
        }
        $stats['generators_by_type'] = $typeBreakdown;
        
        return $stats;
    }

    /**
     * Update type mapping for a generator.
     */
    protected function updateTypeMap(string $type, Generator $generator): void
    {
        if (!isset($this->typeMap[$type])) {
            $this->typeMap[$type] = [];
        }
        
        $this->typeMap[$type][] = $generator;
    }

    /**
     * Rebuild the type map from scratch.
     */
    protected function rebuildTypeMap(): void
    {
        $this->typeMap = [];
        
        // Add core generators
        foreach ($this->generators as $type => $generator) {
            $this->updateTypeMap($type, $generator);
        }
        
        // Add plugin generators
        foreach ($this->pluginGenerators as $pluginGenerators) {
            foreach ($pluginGenerators as $generator) {
                foreach ($generator->types() as $type) {
                    $this->updateTypeMap($type, $generator);
                }
            }
        }
    }

    /**
     * Get generators that should run for the given tree.
     */
    public function getActiveGenerators(Tree $tree): array
    {
        $activeGenerators = [];
        
        // Add core generators
        foreach ($this->generators as $generator) {
            $activeGenerators[] = $generator;
        }
        
        // Add plugin generators that should run
        foreach ($this->getPluginGenerators() as $generator) {
            if ($generator->shouldRun($tree)) {
                $activeGenerators[] = $generator;
            }
        }
        
        return $activeGenerators;
    }

    /**
     * Create an extendable generator from a core generator.
     */
    public function createExtendableGenerator(string $type, Plugin $plugin, array $extensions = []): ?ExtendableGenerator
    {
        $coreGenerator = $this->getCoreGenerator($type);
        
        if (!$coreGenerator) {
            return null;
        }
        
        $extendableGenerator = ExtendableGenerator::wrap($coreGenerator, $plugin, $this->filesystem);
        $extendableGenerator->addExtensions($extensions);
        
        return $extendableGenerator;
    }

    /**
     * Replace a core generator with an extended version.
     */
    public function extendCoreGenerator(string $type, Plugin $plugin, array $extensions): bool
    {
        $extendableGenerator = $this->createExtendableGenerator($type, $plugin, $extensions);
        
        if (!$extendableGenerator) {
            return false;
        }
        
        // Replace the core generator
        $this->generators[$type] = $extendableGenerator;
        
        // Update composite generator
        $this->compositeGenerator->removeGenerator($this->getCoreGenerator($type));
        $this->compositeGenerator->addGenerator($extendableGenerator);
        
        // Update type mappings
        $this->rebuildTypeMap();
        
        return true;
    }

    /**
     * Create a composite generator with multiple generators for the same type.
     */
    public function createCompositeForType(string $type): CompositeGenerator
    {
        $composite = new CompositeGenerator($this->filesystem);
        
        // Add all generators that handle this type
        foreach ($this->getGeneratorsForType($type) as $generator) {
            $composite->addGenerator($generator);
        }
        
        return $composite;
    }

    /**
     * Get generator inheritance chain.
     */
    public function getGeneratorInheritanceChain(PluginGenerator $generator): array
    {
        $chain = [$generator];
        
        if ($generator instanceof ExtendableGenerator) {
            $baseGenerator = $generator->getBaseGenerator();
            if ($baseGenerator instanceof PluginGenerator) {
                $chain = array_merge($this->getGeneratorInheritanceChain($baseGenerator), $chain);
            } else {
                $chain = array_merge([$baseGenerator], $chain);
            }
        }
        
        return $chain;
    }
} 
<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Tree;

class LayoutGenerator extends AbstractClassGenerator implements Generator
{
    protected array $types = ['controllers', 'views'];

    public function output(Tree $tree): array
    {
        $layoutPath = 'resources/views/layouts/app.blade.php';
        
        // Only create the layout if it doesn't exist and views are being generated
        if ($this->filesystem->exists($layoutPath)) {
            $this->output['skipped'][] = $layoutPath;
            return $this->output;
        }

        // Check if any views are being generated
        $hasViews = false;
        foreach ($tree->controllers() as $controller) {
            foreach ($controller->methods() as $statements) {
                foreach ($statements as $statement) {
                    if ($statement instanceof \Blueprint\Models\Statements\RenderStatement) {
                        $hasViews = true;
                        break 3;
                    }
                }
            }
        }

        if (!$hasViews) {
            return $this->output;
        }

        $stub = $this->filesystem->stub('layout.app.stub');
        
        // Create the layouts directory if it doesn't exist
        $layoutsDir = 'resources/views/layouts';
        if (!$this->filesystem->exists($layoutsDir)) {
            $this->filesystem->makeDirectory($layoutsDir, 0755, true);
        }

        $this->create($layoutPath, $stub);
        $this->output['created'][] = $layoutPath;

        return $this->output;
    }
} 
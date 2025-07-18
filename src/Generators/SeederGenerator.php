<?php

namespace Blueprint\Generators;

use Blueprint\Concerns\HandlesImports;
use Blueprint\Concerns\HandlesTraits;
use Blueprint\Contracts\Generator;
use Blueprint\Contracts\Model as BlueprintModel;
use Blueprint\Models\Model;
use Blueprint\Tree;

class SeederGenerator extends AbstractClassGenerator implements Generator
{
    use HandlesImports, HandlesTraits;

    protected array $types = ['seeders'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        $stub = $this->filesystem->stub('seeder.stub');

        foreach ($tree->seeders() as $seederName => $seederData) {
            // Handle both old format (string model names) and new format (seeder objects)
            if (is_string($seederData)) {
                // Old format: seeder is just a model name
                $modelName = $seederData;
            } else {
                // New format: seeder is an object with model property
                $modelName = $seederData['model'] ?? $seederName;
            }
            
            $model = new Model($modelName);
            $path = $this->getPath($model);
            $this->create($path, $this->populateStub($stub, $model));
        }

        return $this->output;
    }

    protected function getPath(BlueprintModel $blueprintModel): string
    {
        $path = $blueprintModel->name();
        if ($blueprintModel->namespace()) {
            $path = str_replace('\\', '/', $blueprintModel->namespace()) . '/' . $path;
        }

        return 'database/seeders/' . $path . 'Seeder.php';
    }

    protected function populateStub(string $stub, BlueprintModel $model): string
    {
        $stub = str_replace('{{ class }}', $model->name() . 'Seeder', $stub);
        $this->addImport($model, 'Illuminate\Database\Seeder');
        $stub = str_replace('//', $this->build($model), $stub);
        $stub = str_replace('use Illuminate\Database\Seeder;', $this->buildImports($model), $stub);

        return $stub;
    }

    protected function build(BlueprintModel $model): string
    {
        $this->addImport($model, $this->tree->fqcnForContext($model->name()));

        return sprintf('%s::factory()->count(5)->create();', class_basename($this->tree->fqcnForContext($model->name())));
    }
}

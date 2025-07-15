<?php

namespace Blueprint\Generators;

use Blueprint\Concerns\HandlesImports;
use Blueprint\Concerns\HandlesTraits;
use Blueprint\Contracts\Generator;
use Blueprint\Models\Controller;
use Blueprint\Models\Policy;
use Blueprint\Models\Statements\DispatchStatement;
use Blueprint\Models\Statements\EloquentStatement;
use Blueprint\Models\Statements\FireStatement;
use Blueprint\Models\Statements\InertiaStatement;
use Blueprint\Models\Statements\QueryStatement;
use Blueprint\Models\Statements\RedirectStatement;
use Blueprint\Models\Statements\RenderStatement;
use Blueprint\Models\Statements\ResourceStatement;
use Blueprint\Models\Statements\RespondStatement;
use Blueprint\Models\Statements\SendStatement;
use Blueprint\Models\Statements\SessionStatement;
use Blueprint\Models\Statements\ValidateStatement;
use Blueprint\Tree;
use Illuminate\Support\Str;

class ControllerGenerator extends AbstractClassGenerator implements Generator
{
    use HandlesImports, HandlesTraits;

    protected array $types = ['controllers'];

    public function output(Tree $tree): array
    {
        $this->tree = $tree;

        $stub = $this->filesystem->stub('controller.class.stub');

        error_log('DEBUG: Tree controllers count: ' . count($tree->controllers()));
        
        /** @var \Blueprint\Models\Controller $controller */
        foreach ($tree->controllers() as $controller) {
            error_log('DEBUG: Processing controller: ' . $controller->className());
            $this->addImport($controller, 'Illuminate\\Http\\Request');
            if ($controller->fullyQualifiedNamespace() !== 'App\\Http\\Controllers') {
                $this->addImport($controller, 'App\\Http\\Controllers\\Controller');
            }
            $path = $this->getPath($controller);
            error_log('DEBUG: Path: ' . $path);

            try {
                $this->create($path, $this->populateStub($stub, $controller));
                error_log('DEBUG: Successfully created controller');
            } catch (\Exception $e) {
                error_log('DEBUG: Exception in create: ' . $e->getMessage());
                throw $e;
            }
        }

        return $this->output;
    }

    protected function populateStub(string $stub, Controller $controller): string
    {
        $stub = str_replace('{{ namespace }}', $controller->fullyQualifiedNamespace(), $stub);
        $stub = str_replace('{{ class }}', $controller->className(), $stub);
        // Fix: Implode methods array to string
        $methods = $this->buildMethods($controller);
        if (is_array($methods)) {
            $methods = implode("\n\n", $methods);
        }
        $stub = str_replace('{{ methods }}', $methods, $stub);
        $stub = str_replace('{{ imports }}', $this->buildImports($controller), $stub);

        return $stub;
    }

    protected function buildMethods(Controller $controller): array
    {
        $methods = [];

        $template = $this->filesystem->stub('controller.method.stub');

        $controllerModelName = Str::singular($controller->prefix());

        if ($controller->policy()?->authorizeResource()) {
            $methods[] = str_replace(
                [
                    '{{ modelClass }}',
                    '{{ modelVariable }}',
                ],
                [
                    Str::studly($controllerModelName),
                    Str::camel($controllerModelName),
                ],
                $this->filesystem->stub('controller.authorize-resource.stub')
            );
        }

        foreach ($controller->methods() as $name => $statements) {
            $method = str_replace('{{ method }}', $name, $template);
            $search = '(Request $request';

            if (in_array($name, ['edit', 'update', 'show', 'destroy'])) {
                $reference = $this->fullyQualifyModelReference($controller->namespace(), $controllerModelName);
                $variable = '$' . Str::camel($controllerModelName);

                $method = str_replace($search, $search . ', ' . $controllerModelName . ' ' . $variable, $method);
                $this->addImport($controller, $reference);
            }

            if ($parent = $controller->parent()) {
                $method = str_replace($search, $search . ', ' . $parent . ' $' . Str::camel($parent), $method);
                $this->addImport($controller, $this->fullyQualifyModelReference($controller->namespace(), $parent));
            }

            $body = '';
            $using_validation = false;

            if ($controller->policy() && !$controller->policy()->authorizeResource()) {
                if (in_array(Policy::$resourceAbilityMap[$name], $controller->policy()->methods())) {
                    $body .= self::INDENT . str_replace(
                        [
                            '{{ method }}',
                            '{{ modelClass }}',
                            '{{ modelVariable }}',
                        ],
                        [
                            $name,
                            Str::studly($controllerModelName),
                            '$' . Str::camel($controllerModelName),
                        ],
                        in_array($name, ['index', 'create', 'store'])
                            ? "Gate::authorize('{{ method }}', {{ modelClass }}::class);"
                            : "Gate::authorize('{{ method }}', {{ modelVariable }});"
                    ) . PHP_EOL . PHP_EOL;
                    $this->addImport($controller, 'Illuminate\Support\Facades\Gate');
                }
            }

            foreach ($statements as $statement) {
                if ($statement instanceof ValidateStatement) {
                    $using_validation = true;
                    $class_name = Str::singular($controller->prefix()) . Str::studly($name) . 'Request';

                    $fqcn = config('blueprint.namespace') . '\\Http\\Requests\\' . ($controller->namespace() ? $controller->namespace() . '\\' : '') . $class_name;

                    $method = str_replace('\Illuminate\Http\Request $request', '\\' . $fqcn . ' $request', $method);
                    $method = str_replace('(Request $request', '(' . $class_name . ' $request', $method);

                    $this->addImport($controller, $fqcn);
                    continue; // Skip output generation for ValidateStatement
                } elseif ($statement instanceof ResourceStatement) {
                    $output = $statement->output([]); // Pass empty array for properties
                } elseif ($statement instanceof EloquentStatement) {
                    $output = $statement->output($controller->prefix(), $name, $using_validation);
                } elseif ($statement instanceof QueryStatement) {
                    $output = $statement->output($controller->prefix());
                } elseif (method_exists($statement, 'output')) {
                    $output = $statement->output();
                } else {
                    $output = (string)$statement;
                }
                if (!is_string($output)) {
                    // file_put_contents('/tmp/controllergenerator_statement_debug.log', "[ERROR] Method: $name, StatementClass: " . (is_object($statement) ? get_class($statement) : gettype($statement)) . ", Non-string output: " . var_export($output, true) . "\n", FILE_APPEND);
                }
                $body .= self::INDENT . $output . PHP_EOL;
            }

            if (!empty($body)) {
                $method = str_replace('{{ body }}', trim($body), $method);
            }

            if ($statement instanceof RespondStatement && $statement->content()) {
                $method = str_replace('): Response' . PHP_EOL, ')' . PHP_EOL, $method);
            } else {
                $returnType = match (true) {
                    $statement instanceof InertiaStatement => 'Inertia\Response',
                    $statement instanceof RenderStatement => 'Illuminate\View\View',
                    $statement instanceof RedirectStatement => 'Illuminate\Http\RedirectResponse',
                    $statement instanceof ResourceStatement => $statement->collection() && !$statement->generateCollectionClass() ? 'Illuminate\Http\Resources\Json\ResourceCollection' : config('blueprint.namespace') . '\\Http\\Resources\\' . ($controller->namespace() ? $controller->namespace() . '\\' : '') . $statement->name(),
                    default => 'Illuminate\Http\Response'
                };

                $method = str_replace('): Response' . PHP_EOL, '): ' . Str::afterLast($returnType, '\\') . PHP_EOL, $method);
                $this->addImport($controller, $returnType);
            }

            $methods[] = PHP_EOL . $method;
        }

        return $methods;
    }

    private function fullyQualifyModelReference(string $sub_namespace, string $model_name): string
    {
        // TODO: get model_name from tree.
        // If not found, assume parallel namespace as controller.
        // Use respond-statement.php as test case.

        /** @var \Blueprint\Models\Model $model */
        $model = $this->tree->modelForContext($model_name);

        if (isset($model)) {
            return $model->fullyQualifiedClassName();
        }

        return sprintf(
            '%s\\%s%s%s',
            config('blueprint.namespace'),
            config('blueprint.models_namespace') ? config('blueprint.models_namespace') . '\\' : '',
            $sub_namespace ? $sub_namespace . '\\' : '',
            $model_name
        );
    }

    private function determineModel(Controller $controller, ?string $reference): string
    {
        if (empty($reference) || $reference === 'id') {
            return $this->fullyQualifyModelReference($controller->namespace(), Str::studly(Str::singular($controller->prefix())));
        }

        if (Str::contains($reference, '.')) {
            return $this->fullyQualifyModelReference($controller->namespace(), Str::studly(Str::before($reference, '.')));
        }

        return $this->fullyQualifyModelReference($controller->namespace(), Str::studly($reference));
    }
}

<?php

namespace Blueprint\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Controller;
use Blueprint\Models\Policy;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ControllerLexer implements Lexer
{
    private StatementLexer $statementLexer;

    public function __construct(StatementLexer $statementLexer)
    {
        $this->statementLexer = $statementLexer;
    }

    public function analyze(array $tokens): array
    {
        $registry = [
            'controllers' => [],
            'policies' => [],
        ];

        if (empty($tokens['controllers'])) {
            return $registry;
        }

        foreach ($tokens['controllers'] as $name => $definition) {
            $controller = new Controller($name);

            // Handle case where definition is a string (e.g., "Foo: resource")
            if (is_string($definition)) {
                if ($definition === 'resource') {
                    $definition = ['resource' => 'web'];
                } else {
                    $definition = ['resource' => $definition];
                }
            }

            if (isset($definition['resource'])) {
                $resource_methods = $this->methodsForResource($definition['resource']);
                $resource_definition = $this->generateResourceTokens($controller, $resource_methods);

                if ($this->hasOnlyApiResourceMethods($resource_methods)) {
                    $controller->setApiResource(true);
                }

                unset($definition['resource']);

                $definition = array_merge($resource_definition, $definition);
            }

            if (isset($definition['invokable'])) {
                $definition['invokable'] === true
                    ? $definition['__invoke'] = ['render' => Str::camel($this->getControllerModelName($controller))]
                    : $definition['__invoke'] = $definition['invokable'];

                unset($definition['invokable']);
            }

            if (isset($definition['meta'])) {
                if (isset($definition['meta']['policies'])) {
                    $authorizeResource = Arr::get($definition, 'meta.policies', true);

                    $policy = new Policy(
                        $controller->prefix(),
                        $authorizeResource === true
                            ? Policy::$supportedMethods
                            : array_unique(
                                array_map(
                                    fn (string $method): string => Policy::$resourceAbilityMap[$method],
                                    preg_split('/,([ \t]+)?/', $definition['meta']['policies'])
                                )
                            ),
                        $authorizeResource === true,
                    );

                    $controller->policy($policy);

                    $registry['policies'][] = $policy;
                }

                if (isset($definition['meta']['parent'])) {
                    $controller->setParent($definition['meta']['parent']);
                }

                unset($definition['meta']);
            }

            foreach ($definition as $method => $body) {
                $controller->addMethod($method, $this->statementLexer->analyze($body));
            }

            $registry['controllers'][$name] = $controller;
        }

        return $registry;
    }

    private function generateResourceTokens(Controller $controller, array $methods)
    {
        return collect($this->resourceTokens())
            ->filter(fn ($statements, $method) => in_array($method, $methods))
            ->mapWithKeys(fn ($statements, $method) => [
                str_replace('api.', '', $method) => collect($statements)->map(function ($statement) use ($controller) {
                    $model = $this->getControllerModelName($controller);

                    return str_replace(
                        ['[singular]', '[plural]'],
                        [Str::camel($model), Str::camel(Str::plural($model))],
                        $statement
                    );
                }),
            ])
            ->toArray();
    }

    private function getControllerModelName(Controller $controller): string
    {
        return Str::singular($controller->prefix());
    }

    private function resourceTokens(): array
    {
        return [
            'index' => [
                'query' => 'all:[plural]',
                'render' => '[singular].index with:[plural]',
            ],
            'create' => [
                'render' => '[singular].create',
            ],
            'store' => [
                'validate' => '[singular]',
                'save' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'show' => [
                'render' => '[singular].show with:[singular]',
            ],
            'edit' => [
                'render' => '[singular].edit with:[singular]',
            ],
            'update' => [
                'validate' => '[singular]',
                'update' => '[singular]',
                'flash' => '[singular].id',
                'redirect' => '[plural].index',
            ],
            'destroy' => [
                'delete' => '[singular]',
                'redirect' => '[plural].index',
            ],
            'api.index' => [
                'query' => 'all:[plural]',
                'resource' => 'collection:[plural]',
            ],
            'api.store' => [
                'validate' => '[singular]',
                'save' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.show' => [
                'resource' => '[singular]',
            ],
            'api.update' => [
                'validate' => '[singular]',
                'update' => '[singular]',
                'resource' => '[singular]',
            ],
            'api.destroy' => [
                'delete' => '[singular]',
                'respond' => 204,
            ],
        ];
    }

    private function methodsForResource(string $type): array
    {
        if ($type === 'api') {
            return ['api.index', 'api.store', 'api.show', 'api.update', 'api.destroy'];
        }

        if ($type === 'web') {
            return ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        }

        return array_map('trim', explode(',', strtolower($type)));
    }

    private function hasOnlyApiResourceMethods(array $methods): bool
    {
        return collect($methods)->every(fn ($item, $key) => Str::startsWith($item, 'api.'));
    }
}

<?php

namespace Blueprint\Models;

use Blueprint\Contracts\Model as BlueprintModel;

class Component implements BlueprintModel
{
    private string $name;

    private string $namespace;

    private array $properties = [];

    private array $methods = [];

    private ?string $framework = null;

    private ?string $type = null;

    private array $props = [];

    private array $state = [];

    private array $styles = [];

    private array $dependencies = [];

    private ?string $layout = null;

    private ?string $route = null;

    private array $api = [];

    public function __construct(string $name)
    {
        $this->name = class_basename($name);
        $this->namespace = trim(implode('\\', array_slice(explode('\\', str_replace('/', '\\', $name)), 0, -1)), '\\');
    }

    public function name(): string
    {
        return $this->name;
    }

    public function className(): string
    {
        return $this->name();
    }

    public function namespace(): string
    {
        if (empty($this->namespace)) {
            return '';
        }

        return $this->namespace;
    }

    public function fullyQualifiedNamespace(): string
    {
        $fqn = config('blueprint.namespace');

        if (config('blueprint.components_namespace')) {
            $fqn .= '\\' . config('blueprint.components_namespace');
        }

        if ($this->namespace) {
            $fqn .= '\\' . $this->namespace;
        }

        return $fqn;
    }

    public function fullyQualifiedClassName(): string
    {
        return $this->fullyQualifiedNamespace() . '\\' . $this->className();
    }

    public function methods(): array
    {
        return $this->methods;
    }

    public function addMethod(string $name, array $statements): void
    {
        $this->methods[$name] = $statements;
    }

    public function setMethods(array $methods): void
    {
        $this->methods = $methods;
    }

    public function properties(): array
    {
        return $this->properties;
    }

    public function addProperty(string $name): void
    {
        $this->properties[$name] = $name;
    }

    public function framework(): ?string
    {
        return $this->framework;
    }

    public function setFramework(string $framework): void
    {
        $this->framework = $framework;
    }

    public function type(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function props(): array
    {
        return $this->props;
    }

    public function setProps(array $props): void
    {
        $this->props = $props;
    }

    public function state(): array
    {
        return $this->state;
    }

    public function setState(array $state): void
    {
        $this->state = $state;
    }

    public function styles(): array
    {
        return $this->styles;
    }

    public function setStyles(array $styles): void
    {
        $this->styles = $styles;
    }

    public function dependencies(): array
    {
        return $this->dependencies;
    }

    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    public function layout(): ?string
    {
        return $this->layout;
    }

    public function setLayout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function route(): ?string
    {
        return $this->route;
    }

    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    public function api(): array
    {
        return $this->api;
    }

    public function setApi(array $api): void
    {
        $this->api = $api;
    }
}

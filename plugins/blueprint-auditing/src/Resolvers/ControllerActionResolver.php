<?php

namespace BlueprintExtensions\Auditing\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class ControllerActionResolver
{
    /**
     * Resolve the controller action for the current request.
     *
     * @return string|null
     */
    public static function resolve(): ?string
    {
        if (!app()->bound('request')) {
            return null;
        }

        $request = app('request');
        
        if (!$request instanceof Request) {
            return null;
        }

        $route = $request->route();
        
        if (!$route instanceof Route) {
            return null;
        }

        $action = $route->getActionName();
        
        // Extract controller and method from the action
        if (str_contains($action, '@')) {
            $parts = explode('@', $action);
            $controller = class_basename($parts[0]);
            $method = $parts[1] ?? 'index';
            return $controller . '@' . $method;
        }
        
        // Handle closure routes
        if (str_contains($action, 'Closure')) {
            return 'Closure@' . $route->getName() ?? 'anonymous';
        }
        
        return $action;
    }
} 
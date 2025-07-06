<?php

namespace BlueprintExtensions\Auditing\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

class RouteNameResolver
{
    /**
     * Resolve the route name for the current request.
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

        return $route->getName();
    }
} 
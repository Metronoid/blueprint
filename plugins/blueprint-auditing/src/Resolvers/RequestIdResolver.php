<?php

namespace BlueprintExtensions\Auditing\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestIdResolver
{
    /**
     * Resolve the request ID for the current request.
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

        // Check if we already have a request ID in the session
        if ($request->session()->has('audit_request_id')) {
            return $request->session()->get('audit_request_id');
        }

        // Generate a new request ID
        $requestId = Str::uuid()->toString();
        
        // Store it in the session for the duration of the request
        $request->session()->put('audit_request_id', $requestId);
        
        return $requestId;
    }
} 
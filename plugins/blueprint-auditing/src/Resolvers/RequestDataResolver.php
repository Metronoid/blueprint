<?php

namespace BlueprintExtensions\Auditing\Resolvers;

use Illuminate\Http\Request;

class RequestDataResolver
{
    /**
     * Resolve the request data for the current request.
     *
     * @param array $excludeFields Fields to exclude from the request data
     * @param array $includeFields Fields to include (if specified, only these are captured)
     * @return array|null
     */
    public static function resolve(array $excludeFields = [], array $includeFields = []): ?array
    {
        if (!app()->bound('request')) {
            return null;
        }

        $request = app('request');
        
        if (!$request instanceof Request) {
            return null;
        }

        // Get all request data
        $data = array_merge($request->all(), $request->query());
        
        // Apply include filter if specified
        if (!empty($includeFields)) {
            $data = array_intersect_key($data, array_flip($includeFields));
        }
        
        // Apply exclude filter
        if (!empty($excludeFields)) {
            $data = array_diff_key($data, array_flip($excludeFields));
        }
        
        // Sanitize sensitive data
        $data = self::sanitizeData($data);
        
        return empty($data) ? null : $data;
    }
    
    /**
     * Sanitize sensitive data from the request.
     *
     * @param array $data
     * @return array
     */
    private static function sanitizeData(array $data): array
    {
        $sensitiveFields = [
            'password', 'password_confirmation', 'current_password',
            'token', 'api_token', 'secret', 'key',
            'credit_card', 'card_number', 'cvv', 'cvc',
            'ssn', 'social_security_number',
            'phone', 'telephone', 'mobile',
            'email', 'email_address'
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***REDACTED***';
            }
        }
        
        return $data;
    }
} 
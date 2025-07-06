<?php

namespace BlueprintExtensions\Auditing\Resolvers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class OriginTypeResolver
{
    /**
     * Resolve the origin type for the current context.
     *
     * @return string
     */
    public static function resolve(): string
    {
        // Check if we're in a console command
        if (App::runningInConsole()) {
            return 'console';
        }
        
        // Check if we're in a queue job
        if (App::bound('queue.connection')) {
            return 'job';
        }
        
        // Check if we're in a scheduled task
        if (App::bound('schedule')) {
            return 'scheduled';
        }
        
        // Check if we have a request
        if (App::bound('request')) {
            $request = App::make('request');
            if ($request instanceof Request) {
                return 'request';
            }
        }
        
        // Check if we're in a test
        if (App::environment('testing')) {
            return 'test';
        }
        
        // Default to manual
        return 'manual';
    }
    
    /**
     * Get additional context for the origin type.
     *
     * @param string $originType
     * @return string|null
     */
    public static function getContext(string $originType): ?string
    {
        switch ($originType) {
            case 'console':
                return self::getConsoleContext();
            case 'job':
                return self::getJobContext();
            case 'scheduled':
                return self::getScheduledContext();
            case 'request':
                return self::getRequestContext();
            case 'test':
                return self::getTestContext();
            default:
                return null;
        }
    }
    
    /**
     * Get console command context.
     *
     * @return string|null
     */
    private static function getConsoleContext(): ?string
    {
        if (!App::runningInConsole()) {
            return null;
        }
        
        $args = $_SERVER['argv'] ?? [];
        if (count($args) >= 2) {
            return implode(' ', array_slice($args, 1));
        }
        
        return null;
    }
    
    /**
     * Get job context.
     *
     * @return string|null
     */
    private static function getJobContext(): ?string
    {
        // This would need to be implemented based on your job structure
        return 'queue_job';
    }
    
    /**
     * Get scheduled task context.
     *
     * @return string|null
     */
    private static function getScheduledContext(): ?string
    {
        return 'scheduled_task';
    }
    
    /**
     * Get request context.
     *
     * @return string|null
     */
    private static function getRequestContext(): ?string
    {
        if (!App::bound('request')) {
            return null;
        }
        
        $request = App::make('request');
        if (!$request instanceof Request) {
            return null;
        }
        
        return $request->method() . ' ' . $request->path();
    }
    
    /**
     * Get test context.
     *
     * @return string|null
     */
    private static function getTestContext(): ?string
    {
        return 'phpunit_test';
    }
} 
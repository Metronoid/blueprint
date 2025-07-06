<?php

namespace BlueprintExtensions\Auditing\Tests\Feature;

use BlueprintExtensions\Auditing\Lexers\AuditingLexer;
use BlueprintExtensions\Auditing\Generators\AuditingGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class OriginTrackingTest extends TestCase
{
    /** @test */
    public function it_parses_basic_origin_tracking_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'email' => 'string',
                    'auditing' => [
                        'origin_tracking' => true
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('auditing', $result);
        $this->assertArrayHasKey('User', $result['auditing']);
        $this->assertArrayHasKey('origin_tracking', $result['auditing']['User']);
        $this->assertTrue($result['auditing']['User']['origin_tracking']['enabled']);
    }

    /** @test */
    public function it_parses_detailed_origin_tracking_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Post' => [
                    'title' => 'string',
                    'content' => 'text',
                    'auditing' => [
                        'origin_tracking' => [
                            'enabled' => true,
                            'track_request' => true,
                            'track_session' => true,
                            'track_route' => true,
                            'track_controller_action' => true,
                            'track_request_data' => true,
                            'track_side_effects' => true,
                            'track_causality_chain' => true,
                            'group_audits' => true,
                            'exclude_request_fields' => ['password', '_token'],
                            'include_request_fields' => ['title', 'content'],
                            'track_origin_types' => ['request', 'console', 'observer']
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $originTracking = $result['auditing']['Post']['origin_tracking'];
        
        $this->assertTrue($originTracking['enabled']);
        $this->assertTrue($originTracking['track_request']);
        $this->assertTrue($originTracking['track_session']);
        $this->assertTrue($originTracking['track_route']);
        $this->assertTrue($originTracking['track_controller_action']);
        $this->assertTrue($originTracking['track_request_data']);
        $this->assertTrue($originTracking['track_side_effects']);
        $this->assertTrue($originTracking['track_causality_chain']);
        $this->assertTrue($originTracking['group_audits']);
        $this->assertEquals(['password', '_token'], $originTracking['exclude_request_fields']);
        $this->assertEquals(['title', 'content'], $originTracking['include_request_fields']);
        $this->assertEquals(['request', 'console', 'observer'], $originTracking['track_origin_types']);
    }

    /** @test */
    public function it_parses_string_format_origin_tracking_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'auditing' => [
                        'origin_tracking' => [
                            'exclude_request_fields' => 'password, _token, _method',
                            'include_request_fields' => 'name, email, bio',
                            'track_origin_types' => 'request, console, job'
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $originTracking = $result['auditing']['User']['origin_tracking'];
        
        $this->assertEquals(['password', '_token', '_method'], $originTracking['exclude_request_fields']);
        $this->assertEquals(['name', 'email', 'bio'], $originTracking['include_request_fields']);
        $this->assertEquals(['request', 'console', 'job'], $originTracking['track_origin_types']);
    }

    /** @test */
    public function it_handles_shorthand_origin_tracking_syntax()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Category' => [
                    'name' => 'string',
                    'auditing' => [
                        'origin_tracking' => 'origin'
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertTrue($result['auditing']['Category']['origin_tracking']['enabled']);
    }

    /** @test */
    public function it_generates_origin_tracking_properties_correctly()
    {
        $filesystem = new Filesystem();
        $generator = new AuditingGenerator($filesystem);

        $originTrackingConfig = [
            'enabled' => true,
            'track_request' => true,
            'track_session' => true,
            'track_route' => true,
            'track_controller_action' => true,
            'track_request_data' => true,
            'track_side_effects' => true,
            'track_causality_chain' => true,
            'group_audits' => true,
            'exclude_request_fields' => ['password', '_token'],
            'include_request_fields' => ['name', 'email'],
            'track_origin_types' => ['request', 'console'],
            'resolvers' => [
                'request_id' => 'CustomRequestIdResolver',
                'route_name' => 'CustomRouteNameResolver'
            ]
        ];

        $result = $this->invokePrivateMethod($generator, 'generateOriginTrackingProperties', [$originTrackingConfig]);

        $this->assertStringContainsString("protected \$originTrackingEnabled = true;", $result);
        $this->assertStringContainsString("protected \$trackRequest = true;", $result);
        $this->assertStringContainsString("protected \$trackSession = true;", $result);
        $this->assertStringContainsString("protected \$trackRoute = true;", $result);
        $this->assertStringContainsString("protected \$trackControllerAction = true;", $result);
        $this->assertStringContainsString("protected \$trackRequestData = true;", $result);
        $this->assertStringContainsString("protected \$trackSideEffects = true;", $result);
        $this->assertStringContainsString("protected \$trackCausalityChain = true;", $result);
        $this->assertStringContainsString("protected \$groupAudits = true;", $result);
        $this->assertStringContainsString("protected \$excludeRequestFields = ['password', '_token'];", $result);
        $this->assertStringContainsString("protected \$includeRequestFields = ['name', 'email'];", $result);
        $this->assertStringContainsString("protected \$trackOriginTypes = ['request', 'console'];", $result);
        $this->assertStringContainsString("protected \$originTrackingResolvers = [", $result);
    }

    /** @test */
    public function it_generates_migration_with_origin_tracking_fields()
    {
        $filesystem = new Filesystem();
        $generator = new AuditingGenerator($filesystem);

        $result = $this->invokePrivateMethod($generator, 'generateAuditsMigrationContent', []);

        // Check for origin tracking fields
        $this->assertStringContainsString("request_id", $result);
        $this->assertStringContainsString("session_id", $result);
        $this->assertStringContainsString("route_name", $result);
        $this->assertStringContainsString("controller_action", $result);
        $this->assertStringContainsString("http_method", $result);
        $this->assertStringContainsString("request_data", $result);
        $this->assertStringContainsString("response_data", $result);
        $this->assertStringContainsString("origin_type", $result);
        $this->assertStringContainsString("origin_context", $result);
        $this->assertStringContainsString("side_effects", $result);
        $this->assertStringContainsString("causality_chain", $result);
        $this->assertStringContainsString("parent_audit_id", $result);
        $this->assertStringContainsString("audit_group_id", $result);
    }

    /**
     * Helper method to invoke private methods for testing.
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
} 
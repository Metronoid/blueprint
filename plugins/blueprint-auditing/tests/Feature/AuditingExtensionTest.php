<?php

namespace BlueprintExtensions\Auditing\Tests\Feature;

use BlueprintExtensions\Auditing\Lexers\AuditingLexer;
use BlueprintExtensions\Auditing\Generators\AuditingGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class AuditingExtensionTest extends TestCase
{
    /** @test */
    public function it_parses_basic_auditing_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'email' => 'string',
                    'auditing' => true
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('auditing', $result);
        $this->assertArrayHasKey('User', $result['auditing']);
        $this->assertTrue($result['auditing']['User']['enabled']);
    }

    /** @test */
    public function it_parses_detailed_auditing_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Post' => [
                    'title' => 'string',
                    'content' => 'text',
                    'auditing' => [
                        'events' => ['created', 'updated'],
                        'exclude' => ['internal_notes'],
                        'strict' => true,
                        'threshold' => 100,
                        'tags' => ['content', 'editorial']
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $auditing = $result['auditing']['Post'];
        
        $this->assertTrue($auditing['enabled']);
        $this->assertEquals(['created', 'updated'], $auditing['events']);
        $this->assertEquals(['internal_notes'], $auditing['exclude']);
        $this->assertTrue($auditing['strict']);
        $this->assertEquals(100, $auditing['threshold']);
        $this->assertEquals(['content', 'editorial'], $auditing['tags']);
    }

    /** @test */
    public function it_parses_string_format_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'auditing' => [
                        'events' => 'created, updated, deleted',
                        'exclude' => 'password, remember_token'
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $auditing = $result['auditing']['User'];
        
        $this->assertEquals(['created', 'updated', 'deleted'], $auditing['events']);
        $this->assertEquals(['password', 'remember_token'], $auditing['exclude']);
    }

    /** @test */
    public function it_handles_shorthand_syntax()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Category' => [
                    'name' => 'string',
                    'auditing' => 'auditing'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertTrue($result['auditing']['Category']['enabled']);
    }

    /** @test */
    public function it_parses_auditing_with_rewind_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'name' => 'string',
                    'price' => 'decimal',
                    'auditing' => [
                        'events' => ['created', 'updated'],
                        'rewind' => [
                            'enabled' => true,
                            'methods' => ['rewindTo', 'rewindSteps'],
                            'max_steps' => 5
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $auditing = $result['auditing']['Product'];
        
        $this->assertTrue($auditing['enabled']);
        $this->assertEquals(['created', 'updated'], $auditing['events']);
        $this->assertArrayHasKey('rewind', $auditing);
        $this->assertTrue($auditing['rewind']['enabled']);
        $this->assertEquals(['rewindTo', 'rewindSteps'], $auditing['rewind']['methods']);
        $this->assertEquals(5, $auditing['rewind']['max_steps']);
    }

    /** @test */
    public function it_parses_combined_auditing_and_rewind_shorthand()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Order' => [
                    'total' => 'decimal',
                    'status' => 'string',
                    'auditing' => [
                        'events' => 'created, updated',
                        'rewind' => true
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $auditing = $result['auditing']['Order'];
        
        $this->assertTrue($auditing['enabled']);
        $this->assertEquals(['created', 'updated'], $auditing['events']);
        $this->assertTrue($auditing['rewind']['enabled']);
    }

    /** @test */
    public function it_generates_types_correctly()
    {
        $filesystem = new Filesystem();
        $generator = new AuditingGenerator($filesystem);
        
        $types = $generator->types();
        
        $this->assertContains('auditing', $types);
    }

    /** @test */
    public function it_handles_empty_tree()
    {
        $filesystem = new Filesystem();
        $generator = new AuditingGenerator($filesystem);
        $tree = new Tree([]);
        
        $output = $generator->output($tree);
        
        $this->assertEmpty($output);
    }

    /** @test */
    public function it_parses_rewind_with_all_configuration_options()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Invoice' => [
                    'number' => 'string',
                    'amount' => 'decimal',
                    'auditing' => [
                        'events' => ['created', 'updated'],
                        'rewind' => [
                            'enabled' => true,
                            'methods' => ['rewindTo', 'rewindToDate', 'rewindSteps'],
                            'validate' => true,
                            'events' => ['rewound'],
                            'exclude' => ['internal_notes', 'metadata'],
                            'include' => ['number', 'amount'],
                            'require_confirmation' => true,
                            'max_steps' => 10,
                            'backup_before_rewind' => false
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $rewind = $result['auditing']['Invoice']['rewind'];
        
        $this->assertTrue($rewind['enabled']);
        $this->assertEquals(['rewindTo', 'rewindToDate', 'rewindSteps'], $rewind['methods']);
        $this->assertTrue($rewind['validate']);
        $this->assertEquals(['rewound'], $rewind['events']);
        $this->assertEquals(['internal_notes', 'metadata'], $rewind['exclude']);
        $this->assertEquals(['number', 'amount'], $rewind['include']);
        $this->assertTrue($rewind['require_confirmation']);
        $this->assertEquals(10, $rewind['max_steps']);
        $this->assertFalse($rewind['backup_before_rewind']);
    }
} 
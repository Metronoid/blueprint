<?php

namespace BlueprintExtensions\Auditing\Tests\Feature;

use BlueprintExtensions\Auditing\Lexers\AuditingLexer;
use BlueprintExtensions\Auditing\Generators\AuditingGenerator;
use BlueprintExtensions\Auditing\Traits\RewindableTrait;
use BlueprintExtensions\Auditing\Events\ModelRewound;
use BlueprintExtensions\Auditing\Exceptions\RewindException;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Mockery;
use Carbon\Carbon;

class RewindFunctionalityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_parses_basic_rewind_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'email' => 'string',
                    'auditing' => [
                        'rewind' => true
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('auditing', $result);
        $this->assertArrayHasKey('User', $result['auditing']);
        $this->assertArrayHasKey('rewind', $result['auditing']['User']);
        $this->assertTrue($result['auditing']['User']['rewind']['enabled']);
    }

    /** @test */
    public function it_parses_detailed_rewind_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Post' => [
                    'title' => 'string',
                    'content' => 'text',
                    'auditing' => [
                        'rewind' => [
                            'enabled' => true,
                            'methods' => ['rewindTo', 'rewindSteps'],
                            'validate' => true,
                            'events' => ['rewound'],
                            'exclude' => ['internal_notes'],
                            'max_steps' => 10,
                            'backup_before_rewind' => true
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $rewind = $result['auditing']['Post']['rewind'];
        
        $this->assertTrue($rewind['enabled']);
        $this->assertEquals(['rewindTo', 'rewindSteps'], $rewind['methods']);
        $this->assertTrue($rewind['validate']);
        $this->assertEquals(['rewound'], $rewind['events']);
        $this->assertEquals(['internal_notes'], $rewind['exclude']);
        $this->assertEquals(10, $rewind['max_steps']);
        $this->assertTrue($rewind['backup_before_rewind']);
    }

    /** @test */
    public function it_parses_string_format_rewind_configuration()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'auditing' => [
                        'rewind' => [
                            'methods' => 'rewindTo, rewindSteps, rewindToDate',
                            'exclude' => 'password, remember_token'
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $rewind = $result['auditing']['User']['rewind'];
        
        $this->assertEquals(['rewindTo', 'rewindSteps', 'rewindToDate'], $rewind['methods']);
        $this->assertEquals(['password', 'remember_token'], $rewind['exclude']);
    }

    /** @test */
    public function it_handles_shorthand_rewind_syntax()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Category' => [
                    'name' => 'string',
                    'auditing' => [
                        'rewind' => 'rewind'
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertTrue($result['auditing']['Category']['rewind']['enabled']);
    }

    /** @test */
    public function it_generates_rewind_configuration_in_model()
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $generator = new AuditingGenerator($filesystem);

        $modelContent = <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email'];
}
PHP;

        $filesystem->shouldReceive('exists')
            ->with('app/Models/User.php')
            ->once()
            ->andReturn(true);

        $filesystem->shouldReceive('get')
            ->with('app/Models/User.php')
            ->once()
            ->andReturn($modelContent);

        $filesystem->shouldReceive('put')
            ->with('app/Models/User.php', Mockery::type('string'))
            ->once();

        // Mock the audit migration check
        $filesystem->shouldReceive('files')
            ->with('database/migrations')
            ->once()
            ->andReturn([]);

        // Mock the audit migration creation
        $filesystem->shouldReceive('put')
            ->with(Mockery::pattern('/database\/migrations\/\d{4}_\d{2}_\d{2}_\d{6}_create_audits_table\.php/'), Mockery::type('string'))
            ->once();

        $config = [
            'enabled' => true,
            'rewind' => [
                'enabled' => true,
                'methods' => ['rewindTo', 'rewindSteps'],
                'validate' => true,
                'max_steps' => 10
            ]
        ];

        $tree = new Tree(['auditing' => ['User' => $config]]);
        $result = $generator->output($tree);

        $this->assertArrayHasKey('app/Models/User.php', $result);
        $this->assertEquals('updated', $result['app/Models/User.php']);
    }

    /** @test */
    public function rewind_trait_provides_correct_methods()
    {
        $trait = $this->getMockForTrait(RewindableTrait::class);
        
        $this->assertTrue(method_exists($trait, 'rewindTo'));
        $this->assertTrue(method_exists($trait, 'rewindToDate'));
        $this->assertTrue(method_exists($trait, 'rewindSteps'));
        $this->assertTrue(method_exists($trait, 'rewindToPrevious'));
        $this->assertTrue(method_exists($trait, 'getRewindableAudits'));
        $this->assertTrue(method_exists($trait, 'previewRewind'));
        $this->assertTrue(method_exists($trait, 'canRewindTo'));
        $this->assertTrue(method_exists($trait, 'getRewindDiff'));
    }

    /** @test */
    public function rewind_to_throws_exception_for_invalid_audit_id()
    {
        $model = $this->getMockForTrait(RewindableTrait::class, [], '', true, true, true, ['audits']);
        
        // Mock the audits relationship
        $auditQuery = Mockery::mock();
        $auditQuery->shouldReceive('find')
            ->with('invalid-id')
            ->andReturn(null);

        $model->method('audits')
            ->willReturn($auditQuery);

        $this->expectException(RewindException::class);
        $this->expectExceptionMessage('Audit with ID invalid-id not found for this model.');

        $model->rewindTo('invalid-id');
    }

    /** @test */
    public function rewind_to_date_throws_exception_for_no_audit_found()
    {
        $model = $this->getMockForTrait(RewindableTrait::class, [], '', true, true, true, ['audits']);
        $date = Carbon::now();
        
        // Mock the audits relationship
        $auditQuery = Mockery::mock();
        $auditQuery->shouldReceive('where')
            ->with('created_at', '<=', $date)
            ->andReturnSelf();
        $auditQuery->shouldReceive('orderBy')
            ->with('created_at', 'desc')
            ->andReturnSelf();
        $auditQuery->shouldReceive('first')
            ->andReturn(null);

        $model->method('audits')
            ->willReturn($auditQuery);

        $this->expectException(RewindException::class);
        $this->expectExceptionMessage("No audit found for date {$date->toDateTimeString()}.");

        $model->rewindToDate($date);
    }

    /** @test */
    public function rewind_steps_throws_exception_for_invalid_steps()
    {
        $model = $this->getMockForTrait(RewindableTrait::class);

        $this->expectException(RewindException::class);
        $this->expectExceptionMessage('Steps must be a positive integer.');

        $model->rewindSteps(0);
    }

    /** @test */
    public function rewind_steps_respects_max_steps_limit()
    {
        // Create a concrete test class to test the trait
        $testClass = new class {
            use RewindableTrait;
            
            public $rewindConfig = [
                'max_steps' => 5
            ];
            
            public function audits()
            {
                return collect();
            }
        };

        $this->expectException(RewindException::class);
        $this->expectExceptionMessage('Cannot rewind more than 5 steps.');

        $testClass->rewindSteps(10);
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
    public function it_handles_empty_tree_for_rewind()
    {
        $filesystem = new Filesystem();
        $generator = new AuditingGenerator($filesystem);
        $tree = new Tree([]);
        
        $output = $generator->output($tree);
        
        $this->assertEmpty($output);
    }

    /** @test */
    public function it_parses_rewind_with_include_attributes()
    {
        $lexer = new AuditingLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'name' => 'string',
                    'price' => 'decimal',
                    'auditing' => [
                        'rewind' => [
                            'include' => ['name', 'price'],
                            'require_confirmation' => true
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $rewind = $result['auditing']['Product']['rewind'];
        
        $this->assertEquals(['name', 'price'], $rewind['include']);
        $this->assertTrue($rewind['require_confirmation']);
    }

    /** @test */
    public function it_generates_rewind_properties_correctly()
    {
        $filesystem = Mockery::mock(Filesystem::class);
        $generator = new AuditingGenerator($filesystem);

        $rewindConfig = [
            'enabled' => true,
            'methods' => ['rewindTo', 'rewindSteps'],
            'validate' => false,
            'events' => ['rewound'],
            'exclude_attributes' => ['password'],
            'max_steps' => 5,
            'backup' => true
        ];

        $result = $this->invokePrivateMethod($generator, 'generateRewindProperties', [$rewindConfig]);

        $this->assertStringContainsString("protected \$rewindEnabled = true;", $result);
        $this->assertStringContainsString("protected \$rewindMethods = ['rewindTo', 'rewindSteps'];", $result);
        $this->assertStringContainsString("protected \$rewindValidate = false;", $result);
        $this->assertStringContainsString("protected \$rewindEvents = ['rewound'];", $result);
        $this->assertStringContainsString("protected \$rewindExcludeAttributes = ['password'];", $result);
        $this->assertStringContainsString("protected \$rewindMaxSteps = 5;", $result);
        $this->assertStringContainsString("protected \$rewindBackup = true;", $result);
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
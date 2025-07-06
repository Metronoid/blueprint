<?php

namespace BlueprintExtensions\Constraints\Tests\Feature;

use BlueprintExtensions\Constraints\Lexers\ConstraintsLexer;
use BlueprintExtensions\Constraints\Generators\ConstraintsGenerator;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConstraintsExtensionTest extends TestCase
{
    #[Test]
    public function it_parses_inline_constraint_syntax()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'name' => 'string:255',
                    'price' => 'decimal:8,2 min:0.01 max:10000',
                    'quantity' => 'integer min:0 max:1000',
                    'rating' => 'integer between:1,5'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Product', $result['constraints']);
        
        $productConstraints = $result['constraints']['Product']['columns'];
        
        // Check price constraints
        $this->assertArrayHasKey('price', $productConstraints);
        $this->assertCount(2, $productConstraints['price']);
        
        $priceConstraints = $productConstraints['price'];
        $this->assertEquals('min', $priceConstraints[0]['type']);
        $this->assertEquals(0.01, $priceConstraints[0]['value']);
        $this->assertEquals('max', $priceConstraints[1]['type']);
        $this->assertEquals(10000, $priceConstraints[1]['value']);
        
        // Check quantity constraints
        $this->assertArrayHasKey('quantity', $productConstraints);
        $this->assertCount(2, $productConstraints['quantity']);
        
        // Check rating constraints
        $this->assertArrayHasKey('rating', $productConstraints);
        $this->assertCount(1, $productConstraints['rating']);
        $this->assertEquals('between', $productConstraints['rating'][0]['type']);
        $this->assertEquals(1, $productConstraints['rating'][0]['min']);
        $this->assertEquals(5, $productConstraints['rating'][0]['max']);
    }

    #[Test]
    public function it_parses_model_level_constraints()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Employee' => [
                    'name' => 'string:255',
                    'salary' => 'decimal:10,2',
                    'department' => 'string:100',
                    'constraints' => [
                        'salary' => [
                            'min:30000',
                            'max:500000'
                        ],
                        'department' => [
                            'in:Engineering,Marketing,Sales'
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Employee', $result['constraints']);
        
        $employeeConstraints = $result['constraints']['Employee']['columns'];
        
        // Check salary constraints
        $this->assertArrayHasKey('salary', $employeeConstraints);
        $this->assertCount(2, $employeeConstraints['salary']);
        
        $salaryConstraints = $employeeConstraints['salary'];
        $this->assertEquals('min', $salaryConstraints[0]['type']);
        $this->assertEquals(30000, $salaryConstraints[0]['value']);
        $this->assertEquals('max', $salaryConstraints[1]['type']);
        $this->assertEquals(500000, $salaryConstraints[1]['value']);
        
        // Check department constraints
        $this->assertArrayHasKey('department', $employeeConstraints);
        $this->assertCount(1, $employeeConstraints['department']);
        $this->assertEquals('in', $employeeConstraints['department'][0]['type']);
        $this->assertEquals(['Engineering', 'Marketing', 'Sales'], $employeeConstraints['department'][0]['values']);
    }

    #[Test]
    public function it_parses_string_constraints()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'email' => 'string:191 unique email',
                    'phone' => 'string:20 regex:^\+?[1-9]\d{1,14}$',
                    'name' => 'string:255 alpha',
                    'username' => 'string:50 alpha_num',
                    'website' => 'string:255 url',
                    'ip_address' => 'string:45 ip'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $userConstraints = $result['constraints']['User']['columns'];
        
        // Check email constraint
        $this->assertArrayHasKey('email', $userConstraints);
        $this->assertEquals('email', $userConstraints['email'][0]['type']);
        
        // Check phone regex constraint
        $this->assertArrayHasKey('phone', $userConstraints);
        $this->assertEquals('regex', $userConstraints['phone'][0]['type']);
        $this->assertEquals('^\+?[1-9]\d{1,14}$', $userConstraints['phone'][0]['pattern']);
        
        // Check alpha constraint
        $this->assertArrayHasKey('name', $userConstraints);
        $this->assertEquals('alpha', $userConstraints['name'][0]['type']);
        
        // Check alpha_num constraint
        $this->assertArrayHasKey('username', $userConstraints);
        $this->assertEquals('alpha_num', $userConstraints['username'][0]['type']);
        
        // Check url constraint
        $this->assertArrayHasKey('website', $userConstraints);
        $this->assertEquals('url', $userConstraints['website'][0]['type']);
        
        // Check ip constraint
        $this->assertArrayHasKey('ip_address', $userConstraints);
        $this->assertEquals('ip', $userConstraints['ip_address'][0]['type']);
    }

    #[Test]
    public function it_parses_date_constraints()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Event' => [
                    'start_date' => 'date after:2020-01-01',
                    'end_date' => 'date before:2030-12-31',
                    'created_at' => 'timestamp date'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $eventConstraints = $result['constraints']['Event']['columns'];
        
        // Check after constraint
        $this->assertArrayHasKey('start_date', $eventConstraints);
        $this->assertEquals('after', $eventConstraints['start_date'][0]['type']);
        $this->assertEquals('2020-01-01', $eventConstraints['start_date'][0]['date']);
        
        // Check before constraint
        $this->assertArrayHasKey('end_date', $eventConstraints);
        $this->assertEquals('before', $eventConstraints['end_date'][0]['type']);
        $this->assertEquals('2030-12-31', $eventConstraints['end_date'][0]['date']);
        
        // Check date constraint
        $this->assertArrayHasKey('created_at', $eventConstraints);
        $this->assertEquals('date', $eventConstraints['created_at'][0]['type']);
    }

    #[Test]
    public function it_parses_list_constraints()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'status' => 'enum:active,inactive,discontinued in:active,inactive,discontinued',
                    'category' => 'string:50 not_in:restricted,banned'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $productConstraints = $result['constraints']['Product']['columns'];
        
        // Check in constraint
        $this->assertArrayHasKey('status', $productConstraints);
        $this->assertEquals('in', $productConstraints['status'][0]['type']);
        $this->assertEquals(['active', 'inactive', 'discontinued'], $productConstraints['status'][0]['values']);
        
        // Check not_in constraint
        $this->assertArrayHasKey('category', $productConstraints);
        $this->assertEquals('not_in', $productConstraints['category'][0]['type']);
        $this->assertEquals(['restricted', 'banned'], $productConstraints['category'][0]['values']);
    }

    #[Test]
    public function it_preserves_original_column_definitions()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'price' => 'decimal:8,2 min:0.01 max:10000 nullable',
                    'quantity' => 'integer min:0 max:1000 default:0'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        // Check that original column definitions are preserved for other lexers
        $this->assertEquals('decimal:8,2 min:0.01 max:10000 nullable', $tokens['models']['Product']['price']);
        $this->assertEquals('integer min:0 max:1000 default:0', $tokens['models']['Product']['quantity']);
        
        // But constraints should still be parsed correctly
        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Product', $result['constraints']);
        $productConstraints = $result['constraints']['Product']['columns'];
        $this->assertArrayHasKey('price', $productConstraints);
        $this->assertArrayHasKey('quantity', $productConstraints);
    }

    #[Test]
    public function it_generates_database_constraints()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $generator = new ConstraintsGenerator($filesystem);
        
        // Set configuration for the generator
        $generator->setConfig([
            'generate_database_constraints' => true,
            'generate_validation_rules' => false,
            'generate_model_mutators' => false,
            'database_constraints' => [
                'min' => 'CHECK ({column} >= {value})',
                'max' => 'CHECK ({column} <= {value})',
                'between' => 'CHECK ({column} BETWEEN {min} AND {max})',
            ]
        ]);
        
        $treeData = [
            'models' => [],
            'controllers' => [],
            'constraints' => [
                'Product' => [
                    'columns' => [
                        'price' => [
                            ['type' => 'min', 'value' => 0.01],
                            ['type' => 'max', 'value' => 10000]
                        ],
                        'quantity' => [
                            ['type' => 'between', 'min' => 0, 'max' => 1000]
                        ]
                    ]
                ]
            ]
        ];

        $tree = new Tree($treeData);
        
        // Mock filesystem operations
        $filesystem->expects($this->once())
                   ->method('put')
                   ->with(
                       $this->matchesRegularExpression('/database\/migrations\/.*_add_constraints_to_products_table\.php$/'),
                       $this->stringContains('CHECK (price >= 0.01)')
                   );

        $output = $generator->output($tree);
        
        $this->assertNotEmpty($output);
        $this->assertArrayHasKey(array_keys($output)[0], $output);
        $this->assertEquals('created', reset($output));
    }

    #[Test]
    public function it_generates_validation_rules()
    {
        $filesystem = $this->createMock(Filesystem::class);
        $generator = new ConstraintsGenerator($filesystem);
        
        // Set configuration for the generator
        $generator->setConfig([
            'generate_database_constraints' => false,
            'generate_validation_rules' => true,
            'generate_model_mutators' => false,
            'validation_rules' => [
                'min' => 'min:{value}',
                'max' => 'max:{value}',
                'email' => 'email',
            ]
        ]);
        
        $treeData = [
            'models' => [],
            'controllers' => [],
            'constraints' => [
                'Product' => [
                    'columns' => [
                        'price' => [
                            ['type' => 'min', 'value' => 0.01],
                            ['type' => 'max', 'value' => 10000]
                        ],
                        'email' => [
                            ['type' => 'email']
                        ]
                    ]
                ]
            ]
        ];

        $tree = new Tree($treeData);
        
        // Mock filesystem operations for validation rules
        $filesystem->expects($this->once())
                   ->method('exists')
                   ->with('app/Http/Requests')
                   ->willReturn(false);

        $filesystem->expects($this->once())
                   ->method('put')
                   ->with(
                       'app/Rules/ProductConstraintRules.php',
                       $this->stringContains("'price' => 'min:0.01|max:10000'")
                   );

        $output = $generator->output($tree);
        
        $this->assertNotEmpty($output);
    }

    #[Test]
    public function it_generates_types_correctly()
    {
        $filesystem = new Filesystem();
        $generator = new ConstraintsGenerator($filesystem);
        
        $types = $generator->types();
        
        $this->assertContains('constraints', $types);
    }

    #[Test]
    public function it_handles_empty_tree()
    {
        $filesystem = new Filesystem();
        $generator = new ConstraintsGenerator($filesystem);
        $tree = new Tree([
            'models' => [],
            'controllers' => []
        ]);
        
        $output = $generator->output($tree);
        
        $this->assertEmpty($output);
    }

    #[Test]
    public function it_handles_complex_regex_patterns()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'phone' => 'string:20 regex:^\+?[1-9]\d{1,14}$',
                    'postal_code' => 'string:10 regex:^[A-Z]\d[A-Z] \d[A-Z]\d$'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $userConstraints = $result['constraints']['User']['columns'];
        
        // Check phone regex
        $this->assertEquals('regex', $userConstraints['phone'][0]['type']);
        $this->assertEquals('^\+?[1-9]\d{1,14}$', $userConstraints['phone'][0]['pattern']);
        
        // Check postal code regex
        $this->assertEquals('regex', $userConstraints['postal_code'][0]['type']);
        $this->assertEquals('^[A-Z]\d[A-Z] \d[A-Z]\d$', $userConstraints['postal_code'][0]['pattern']);
    }

    #[Test]
    public function it_handles_multiple_constraints_on_single_column()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'User' => [
                    'age' => 'integer min:13 max:120 between:13,120',
                    'email' => 'string:191 unique email regex:^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$'
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $userConstraints = $result['constraints']['User']['columns'];
        
        // Check age has multiple constraints
        $this->assertArrayHasKey('age', $userConstraints);
        $this->assertCount(3, $userConstraints['age']); // min, max, between
        
        // Check email has multiple constraints
        $this->assertArrayHasKey('email', $userConstraints);
        $this->assertCount(2, $userConstraints['email']); // email, regex
    }

    #[Test]
    public function it_supports_structured_format_with_columns_key()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Product' => [
                    'columns' => [
                        'name' => 'string:255',
                        'price' => 'decimal:8,2 min:0.01 max:10000',
                        'quantity' => 'integer min:0 max:1000',
                        'rating' => 'integer between:1,5'
                    ],
                    'relationships' => [
                        'belongsTo' => 'Category'
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Product', $result['constraints']);
        
        $productConstraints = $result['constraints']['Product']['columns'];
        
        // Check price constraints
        $this->assertArrayHasKey('price', $productConstraints);
        $this->assertCount(2, $productConstraints['price']);
        
        $priceConstraints = $productConstraints['price'];
        $this->assertEquals('min', $priceConstraints[0]['type']);
        $this->assertEquals(0.01, $priceConstraints[0]['value']);
        $this->assertEquals('max', $priceConstraints[1]['type']);
        $this->assertEquals(10000, $priceConstraints[1]['value']);
        
        // Check quantity constraints
        $this->assertArrayHasKey('quantity', $productConstraints);
        $this->assertCount(2, $productConstraints['quantity']);
        
        // Check rating constraints
        $this->assertArrayHasKey('rating', $productConstraints);
        $this->assertCount(1, $productConstraints['rating']);
        $this->assertEquals('between', $productConstraints['rating'][0]['type']);
        $this->assertEquals(1, $productConstraints['rating'][0]['min']);
        $this->assertEquals(5, $productConstraints['rating'][0]['max']);

        // Verify original tokens are preserved
        $this->assertEquals('decimal:8,2 min:0.01 max:10000', $tokens['models']['Product']['columns']['price']);
        $this->assertEquals('integer min:0 max:1000', $tokens['models']['Product']['columns']['quantity']);
    }

    #[Test]
    public function it_supports_structured_format_with_model_level_constraints()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Employee' => [
                    'columns' => [
                        'name' => 'string:255',
                        'email' => 'string:191 unique',
                        'salary' => 'decimal:10,2',
                        'department' => 'string:100',
                        'hire_date' => 'date',
                        'performance_rating' => 'integer'
                    ],
                    'constraints' => [
                        'salary' => [
                            'min:30000',
                            'max:500000'
                        ],
                        'performance_rating' => [
                            'between:1,10'
                        ],
                        'department' => [
                            'in:Engineering,Marketing,Sales,HR,Finance'
                        ],
                        'hire_date' => [
                            'after:2020-01-01',
                            'before:today'
                        ]
                    ],
                    'relationships' => [
                        'belongsTo' => 'Department'
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Employee', $result['constraints']);
        
        $employeeConstraints = $result['constraints']['Employee']['columns'];
        
        // Check salary constraints
        $this->assertArrayHasKey('salary', $employeeConstraints);
        $this->assertCount(2, $employeeConstraints['salary']);
        
        $salaryConstraints = $employeeConstraints['salary'];
        $this->assertEquals('min', $salaryConstraints[0]['type']);
        $this->assertEquals(30000, $salaryConstraints[0]['value']);
        $this->assertEquals('max', $salaryConstraints[1]['type']);
        $this->assertEquals(500000, $salaryConstraints[1]['value']);
        
        // Check performance rating constraints
        $this->assertArrayHasKey('performance_rating', $employeeConstraints);
        $this->assertCount(1, $employeeConstraints['performance_rating']);
        $this->assertEquals('between', $employeeConstraints['performance_rating'][0]['type']);
        $this->assertEquals(1, $employeeConstraints['performance_rating'][0]['min']);
        $this->assertEquals(10, $employeeConstraints['performance_rating'][0]['max']);
        
        // Check department constraints
        $this->assertArrayHasKey('department', $employeeConstraints);
        $this->assertCount(1, $employeeConstraints['department']);
        $this->assertEquals('in', $employeeConstraints['department'][0]['type']);
        $this->assertEquals(['Engineering', 'Marketing', 'Sales', 'HR', 'Finance'], $employeeConstraints['department'][0]['values']);
        
        // Check hire_date constraints
        $this->assertArrayHasKey('hire_date', $employeeConstraints);
        $this->assertCount(2, $employeeConstraints['hire_date']);
        $this->assertEquals('after', $employeeConstraints['hire_date'][0]['type']);
        $this->assertEquals('2020-01-01', $employeeConstraints['hire_date'][0]['date']);
        $this->assertEquals('before', $employeeConstraints['hire_date'][1]['type']);
        $this->assertEquals('today', $employeeConstraints['hire_date'][1]['date']);

        // Verify constraints key is preserved (not consumed by lexer)
        $this->assertArrayHasKey('constraints', $tokens['models']['Employee']);
        
        // Verify other keys are preserved
        $this->assertArrayHasKey('columns', $tokens['models']['Employee']);
        $this->assertArrayHasKey('relationships', $tokens['models']['Employee']);
    }

    #[Test]
    public function it_supports_mixed_inline_and_model_level_constraints_in_structured_format()
    {
        $lexer = new ConstraintsLexer();
        
        $tokens = [
            'models' => [
                'Order' => [
                    'columns' => [
                        'order_number' => 'string:20 unique',
                        'total' => 'decimal:10,2 min:0', // inline constraint
                        'discount' => 'decimal:5,2', // model-level constraint
                        'status' => 'enum:pending,processing,shipped,delivered,cancelled',
                        'user_id' => 'id foreign:users'
                    ],
                    'constraints' => [
                        'discount' => [
                            'between:0,100'
                        ],
                        'status' => [
                            'in:pending,processing,shipped,delivered,cancelled'
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $orderConstraints = $result['constraints']['Order']['columns'];
        
        // Check inline constraint (total)
        $this->assertArrayHasKey('total', $orderConstraints);
        $this->assertCount(1, $orderConstraints['total']);
        $this->assertEquals('min', $orderConstraints['total'][0]['type']);
        $this->assertEquals(0, $orderConstraints['total'][0]['value']);
        
        // Check model-level constraint (discount)
        $this->assertArrayHasKey('discount', $orderConstraints);
        $this->assertCount(1, $orderConstraints['discount']);
        $this->assertEquals('between', $orderConstraints['discount'][0]['type']);
        $this->assertEquals(0, $orderConstraints['discount'][0]['min']);
        $this->assertEquals(100, $orderConstraints['discount'][0]['max']);
        
        // Check model-level constraint (status)
        $this->assertArrayHasKey('status', $orderConstraints);
        $this->assertCount(1, $orderConstraints['status']);
        $this->assertEquals('in', $orderConstraints['status'][0]['type']);
        $this->assertEquals(['pending', 'processing', 'shipped', 'delivered', 'cancelled'], $orderConstraints['status'][0]['values']);

        // Verify original column definitions are preserved
        $this->assertEquals('decimal:10,2 min:0', $tokens['models']['Order']['columns']['total']);
        $this->assertEquals('decimal:5,2', $tokens['models']['Order']['columns']['discount']);
    }

    #[Test]
    public function it_maintains_backward_compatibility_with_legacy_format()
    {
        $lexer = new ConstraintsLexer();
        
        // This is the old format without columns: key
        $tokens = [
            'models' => [
                'Product' => [
                    'name' => 'string:255',
                    'price' => 'decimal:8,2 min:0.01 max:10000',
                    'quantity' => 'integer min:0 max:1000',
                    'constraints' => [
                        'price' => [
                            'between:0.01,10000'
                        ]
                    ]
                ]
            ]
        ];

        $result = $lexer->analyze($tokens);

        $this->assertArrayHasKey('constraints', $result);
        $this->assertArrayHasKey('Product', $result['constraints']);
        
        $productConstraints = $result['constraints']['Product']['columns'];
        
        // Should have both inline and model-level constraints
        $this->assertArrayHasKey('price', $productConstraints);
        
        // The inline constraints should be parsed
        $inlineConstraints = array_filter($productConstraints['price'], fn($c) => in_array($c['type'], ['min', 'max']));
        $this->assertCount(2, $inlineConstraints);
        
        // The model-level constraints should also be parsed
        $modelLevelConstraints = array_filter($productConstraints['price'], fn($c) => $c['type'] === 'between');
        $this->assertCount(1, $modelLevelConstraints);
    }
} 
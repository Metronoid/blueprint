<?php

namespace BlueprintExtensions\StateMachine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use BlueprintExtensions\StateMachine\Lexers\StateMachineLexer;

class LexerTest extends TestCase
{
    private StateMachineLexer $lexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new StateMachineLexer();
    }

    public function test_analyze_returns_empty_array_when_no_models()
    {
        $tokens = [];
        $result = $this->lexer->analyze($tokens);
        $this->assertEquals([], $result);
    }

    public function test_analyze_returns_empty_array_when_no_state_machines()
    {
        $tokens = [
            'models' => [
                'User' => [
                    'name' => 'string',
                    'email' => 'string',
                ],
            ],
        ];
        $result = $this->lexer->analyze($tokens);
        $this->assertEquals([], $result);
    }

    public function test_analyze_parses_basic_state_machine()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'status' => 'enum:pending,processing',
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $this->assertArrayHasKey('state_machines', $result);
        $this->assertArrayHasKey('Order', $result['state_machines']);
        
        $stateMachine = $result['state_machines']['Order'];
        $this->assertEquals('status', $stateMachine['field']);
        $this->assertEquals('pending', $stateMachine['initial']);
        $this->assertArrayHasKey('transitions', $stateMachine);
        $this->assertArrayHasKey('process', $stateMachine['transitions']);
    }

    public function test_analyze_uses_default_field_when_not_specified()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $stateMachine = $result['state_machines']['Order'];
        $this->assertEquals('status', $stateMachine['field']); // Default field
    }

    public function test_analyze_parses_string_format_transitions()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => 'pending -> processing',
                            'ship' => 'processing -> shipped',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $transitions = $result['state_machines']['Order']['transitions'];
        
        $this->assertEquals(['pending'], $transitions['process']['from']);
        $this->assertEquals('processing', $transitions['process']['to']);
        
        $this->assertEquals(['processing'], $transitions['ship']['from']);
        $this->assertEquals('shipped', $transitions['ship']['to']);
    }

    public function test_analyze_parses_multiple_from_states_in_string_format()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'cancel' => 'pending, processing -> cancelled',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $transitions = $result['state_machines']['Order']['transitions'];
        $this->assertEquals(['pending', 'processing'], $transitions['cancel']['from']);
        $this->assertEquals('cancelled', $transitions['cancel']['to']);
    }

    public function test_analyze_extracts_callback_types_and_transitions()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                        'callbacks' => [
                            'before_process' => 'validatePayment',
                            'after_process' => 'sendNotification',
                            'before_ship' => 'checkInventory',
                            'after_ship' => 'updateTracking',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $callbacks = $result['state_machines']['Order']['callbacks'];
        
        $this->assertArrayHasKey('process', $callbacks);
        $this->assertArrayHasKey('ship', $callbacks);
        
        $this->assertEquals('validatePayment', $callbacks['process']['before']['method']);
        $this->assertEquals('sendNotification', $callbacks['process']['after']['method']);
        
        $this->assertEquals('checkInventory', $callbacks['ship']['before']['method']);
        $this->assertEquals('updateTracking', $callbacks['ship']['after']['method']);
    }

    public function test_analyze_handles_array_callback_configuration()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                        'callbacks' => [
                            'before_process' => [
                                'method' => 'validatePayment',
                                'parameters' => ['amount', 'currency'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $callbacks = $result['state_machines']['Order']['callbacks'];
        $processCallback = $callbacks['process']['before'];
        
        $this->assertEquals('validatePayment', $processCallback['method']);
        $this->assertEquals(['amount', 'currency'], $processCallback['parameters']);
    }

    public function test_analyze_handles_array_guard_configuration()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                        'guards' => [
                            'process' => [
                                'method' => 'hasValidPayment',
                                'parameters' => ['amount'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $guards = $result['state_machines']['Order']['guards'];
        $processGuard = $guards['process'];
        
        $this->assertEquals('hasValidPayment', $processGuard['method']);
        $this->assertEquals(['amount'], $processGuard['parameters']);
    }

    public function test_analyze_applies_default_configuration_values()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $stateMachine = $result['state_machines']['Order'];
        
        // These should use default values from config
        $this->assertTrue($stateMachine['track_history']);
        $this->assertTrue($stateMachine['validate_transitions']);
        $this->assertTrue($stateMachine['fire_events']);
    }
} 
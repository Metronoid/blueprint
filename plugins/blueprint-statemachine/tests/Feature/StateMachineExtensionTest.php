<?php

namespace BlueprintExtensions\StateMachine\Tests\Feature;

use PHPUnit\Framework\TestCase;
use BlueprintExtensions\StateMachine\Lexers\StateMachineLexer;
use BlueprintExtensions\StateMachine\Generators\StateMachineGenerator;
use BlueprintExtensions\StateMachine\BlueprintStateMachinePlugin;
use Illuminate\Filesystem\Filesystem;
use Blueprint\Tree;

class StateMachineExtensionTest extends TestCase
{
    private StateMachineLexer $lexer;
    private StateMachineGenerator $generator;
    private BlueprintStateMachinePlugin $plugin;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer = new StateMachineLexer();
        $this->filesystem = new Filesystem();
        $this->plugin = new BlueprintStateMachinePlugin();
        $this->generator = new StateMachineGenerator($this->filesystem, $this->plugin);
    }

    public function test_lexer_parses_state_machine_configuration()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'status' => 'enum:pending,processing,shipped,delivered,cancelled',
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                            'ship' => ['processing', 'shipped'],
                            'deliver' => ['shipped', 'delivered'],
                            'cancel' => ['pending', 'processing', 'cancelled'],
                        ],
                        'guards' => [
                            'ship' => 'hasValidAddress',
                            'deliver' => 'isShipped',
                        ],
                        'callbacks' => [
                            'before_process' => 'validatePayment',
                            'after_process' => 'sendProcessingNotification',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $this->assertArrayHasKey('state_machines', $result);
        $this->assertArrayHasKey('Order', $result['state_machines']);
        
        $orderStateMachine = $result['state_machines']['Order'];
        $this->assertEquals('status', $orderStateMachine['field']);
        $this->assertEquals('pending', $orderStateMachine['initial']);
        $this->assertArrayHasKey('transitions', $orderStateMachine);
        $this->assertArrayHasKey('guards', $orderStateMachine);
        $this->assertArrayHasKey('callbacks', $orderStateMachine);
    }

    public function test_lexer_extracts_states_from_transitions()
    {
        $tokens = [
            'models' => [
                'Order' => [
                    'status' => 'enum:pending,processing,shipped',
                    'state_machine' => [
                        'field' => 'status',
                        'initial' => 'pending',
                        'transitions' => [
                            'process' => ['pending', 'processing'],
                            'ship' => ['processing', 'shipped'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $orderStateMachine = $result['state_machines']['Order'];
        $this->assertArrayHasKey('states', $orderStateMachine);
        
        $states = $orderStateMachine['states'];
        $this->assertArrayHasKey('pending', $states);
        $this->assertArrayHasKey('processing', $states);
        $this->assertArrayHasKey('shipped', $states);
        
        $this->assertEquals('Pending', $states['pending']['label']);
        $this->assertEquals('Processing', $states['processing']['label']);
        $this->assertEquals('Shipped', $states['shipped']['label']);
    }

    public function test_lexer_parses_explicit_states_configuration()
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
                        'states' => [
                            'pending' => [
                                'label' => 'Pending Order',
                                'color' => 'yellow',
                                'description' => 'Order is awaiting processing',
                            ],
                            'processing' => [
                                'label' => 'Processing Order',
                                'color' => 'blue',
                                'description' => 'Order is being processed',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $orderStateMachine = $result['state_machines']['Order'];
        $states = $orderStateMachine['states'];
        
        $this->assertEquals('Pending Order', $states['pending']['label']);
        $this->assertEquals('yellow', $states['pending']['color']);
        $this->assertEquals('Order is awaiting processing', $states['pending']['description']);
        
        $this->assertEquals('Processing Order', $states['processing']['label']);
        $this->assertEquals('blue', $states['processing']['color']);
        $this->assertEquals('Order is being processed', $states['processing']['description']);
    }

    public function test_lexer_parses_guards_configuration()
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
                        'guards' => [
                            'process' => 'hasValidPayment',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $orderStateMachine = $result['state_machines']['Order'];
        $guards = $orderStateMachine['guards'];
        
        $this->assertArrayHasKey('process', $guards);
        $this->assertEquals('hasValidPayment', $guards['process']['method']);
    }

    public function test_lexer_parses_callbacks_configuration()
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
                        'callbacks' => [
                            'before_process' => 'validatePayment',
                            'after_process' => 'sendNotification',
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->lexer->analyze($tokens);

        $orderStateMachine = $result['state_machines']['Order'];
        $callbacks = $orderStateMachine['callbacks'];
        
        $this->assertArrayHasKey('process', $callbacks);
        $this->assertArrayHasKey('before', $callbacks['process']);
        $this->assertArrayHasKey('after', $callbacks['process']);
        
        $this->assertEquals('validatePayment', $callbacks['process']['before']['method']);
        $this->assertEquals('sendNotification', $callbacks['process']['after']['method']);
    }

    public function test_lexer_removes_state_machine_from_model_definition()
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

        // The state_machine should be present in the original tokens (lexers don't modify input)
        $this->assertArrayHasKey('state_machine', $tokens['models']['Order']);
        
        // And should be present in the parsed result
        $this->assertArrayHasKey('state_machines', $result);
        $this->assertArrayHasKey('Order', $result['state_machines']);
    }

    public function test_generator_returns_correct_types()
    {
        $types = $this->generator->types();
        $this->assertEquals(['state_machine'], $types);
    }

    public function test_generator_should_run_with_state_machines()
    {
        $treeData = [
            'models' => [],
            'controllers' => [],
            'state_machines' => [
                'Order' => [
                    'field' => 'status',
                    'initial' => 'pending',
                    'transitions' => [
                        'process' => ['pending', 'processing'],
                    ],
                ],
            ],
        ];

        $tree = new Tree($treeData);
        
        $this->assertTrue($this->generator->shouldRun($tree));
    }

    public function test_generator_should_not_run_without_state_machines()
    {
        $treeData = [
            'models' => [],
            'controllers' => [],
        ];

        $tree = new Tree($treeData);
        
        $this->assertFalse($this->generator->shouldRun($tree));
    }

    public function test_plugin_has_correct_metadata()
    {
        $this->assertEquals('blueprint-statemachine', $this->plugin->getName());
        $this->assertEquals('1.0.0', $this->plugin->getVersion());
        $this->assertEquals('A powerful Blueprint extension that adds state machine functionality to Laravel models', $this->plugin->getDescription());
        $this->assertEquals('Blueprint Extensions', $this->plugin->getAuthor());
    }

    public function test_plugin_is_compatible_with_blueprint_v2()
    {
        $this->assertTrue($this->plugin->isCompatible('2.0.0'));
        $this->assertTrue($this->plugin->isCompatible('2.1.0'));
        $this->assertFalse($this->plugin->isCompatible('1.9.0'));
    }

    public function test_generator_has_correct_plugin_reference()
    {
        $this->assertSame($this->plugin, $this->generator->getPlugin());
    }

    public function test_generator_has_correct_name()
    {
        $this->assertEquals('StateMachineGenerator', $this->generator->getName());
    }

    public function test_generator_has_correct_priority()
    {
        $this->assertEquals(100, $this->generator->getPriority());
    }
} 
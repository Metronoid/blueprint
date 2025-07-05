<?php

namespace Tests\Feature\Plugin;

use Blueprint\Exceptions\ValidationException;
use Blueprint\Plugin\ConfigValidator;
use Tests\TestCase;

class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigValidator();
    }

    /** @test */
    public function it_returns_config_unchanged_when_no_schema_registered()
    {
        $config = ['key' => 'value'];
        
        $result = $this->validator->validate('unknown-plugin', $config);
        
        $this->assertEquals($config, $result);
    }

    /** @test */
    public function it_can_register_and_retrieve_schemas()
    {
        $schema = ['type' => 'object', 'properties' => []];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->assertTrue($this->validator->hasSchema('test-plugin'));
        $this->assertEquals($schema, $this->validator->getSchema('test-plugin'));
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'version' => ['type' => 'string'],
            ],
            'required' => ['name', 'version'],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Required field 'version' is missing");
        
        $this->validator->validate('test-plugin', ['name' => 'test']);
    }

    /** @test */
    public function it_validates_field_types()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
                'enabled' => ['type' => 'boolean'],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'count' must be of type 'integer'");
        
        $this->validator->validate('test-plugin', [
            'name' => 'test',
            'count' => 'not-a-number',
            'enabled' => true,
        ]);
    }

    /** @test */
    public function it_validates_string_constraints()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 10,
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'name' must be at least 3 characters long");
        
        $this->validator->validate('test-plugin', ['name' => 'ab']);
    }

    /** @test */
    public function it_validates_array_constraints()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'maxItems' => 5,
                    'uniqueItems' => true,
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'items' must have at least 2 items");
        
        $this->validator->validate('test-plugin', ['items' => ['one']]);
    }

    /** @test */
    public function it_validates_numeric_constraints()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'priority' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'priority' must be at least 1");
        
        $this->validator->validate('test-plugin', ['priority' => 0]);
    }

    /** @test */
    public function it_validates_enum_values()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'format' => [
                    'enum' => ['json', 'yaml', 'xml'],
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'format' must be one of: json, yaml, xml");
        
        $this->validator->validate('test-plugin', ['format' => 'csv']);
    }

    /** @test */
    public function it_validates_pattern_matching()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'version' => [
                    'type' => 'string',
                    'pattern' => '/^\d+\.\d+\.\d+$/',
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'version' does not match required pattern");
        
        $this->validator->validate('test-plugin', ['version' => '1.0']);
    }

    /** @test */
    public function it_applies_default_values()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'enabled' => ['type' => 'boolean', 'default' => true],
                'priority' => ['type' => 'integer', 'default' => 100],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $result = $this->validator->validate('test-plugin', ['name' => 'test']);
        
        $this->assertEquals([
            'name' => 'test',
            'enabled' => true,
            'priority' => 100,
        ], $result);
    }

    /** @test */
    public function it_validates_nested_array_items()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'generators' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'minLength' => 1,
                    ],
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'generators[1]' must be at least 1 characters long");
        
        $this->validator->validate('test-plugin', [
            'generators' => ['valid-generator', ''],
        ]);
    }

    /** @test */
    public function it_validates_custom_validation_functions()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'custom_field' => [
                    'type' => 'string',
                    'validate' => function ($value) {
                        return $value !== 'forbidden' ? true : 'Value cannot be "forbidden"';
                    },
                ],
            ],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Field \'custom_field\' validation failed: Value cannot be "forbidden"');
        
        $this->validator->validate('test-plugin', ['custom_field' => 'forbidden']);
    }

    /** @test */
    public function it_passes_valid_configuration()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'minLength' => 1],
                'enabled' => ['type' => 'boolean', 'default' => true],
                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'generators' => ['type' => 'array', 'items' => ['type' => 'string']],
                'format' => ['enum' => ['json', 'yaml', 'xml']],
            ],
            'required' => ['name'],
        ];
        
        $this->validator->registerSchema('test-plugin', $schema);
        
        $config = [
            'name' => 'test-plugin',
            'priority' => 200,
            'generators' => ['models', 'controllers'],
            'format' => 'json',
        ];
        
        $result = $this->validator->validate('test-plugin', $config);
        
        $this->assertEquals([
            'name' => 'test-plugin',
            'enabled' => true, // default applied
            'priority' => 200,
            'generators' => ['models', 'controllers'],
            'format' => 'json',
        ], $result);
    }

    /** @test */
    public function schema_builder_creates_correct_schema()
    {
        $schema = ConfigValidator::schema()
            ->string('name', ['required' => true, 'minLength' => 1])
            ->boolean('enabled', ['default' => true])
            ->integer('priority', ['minimum' => 1, 'maximum' => 1000])
            ->array('generators', ['items' => ['type' => 'string']])
            ->enum('format', ['json', 'yaml', 'xml'], ['default' => 'json'])
            ->build();
        
        $expected = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'required' => true, 'minLength' => 1],
                'enabled' => ['type' => 'boolean', 'default' => true],
                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'generators' => ['type' => 'array', 'items' => ['type' => 'string']],
                'format' => ['enum' => ['json', 'yaml', 'xml'], 'default' => 'json'],
            ],
            'required' => ['name'],
        ];
        
        $this->assertEquals($expected, $schema);
    }
} 
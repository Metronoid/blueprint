<?php

namespace Tests\Feature\Lexers;

use Blueprint\Lexers\ModelLexer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Blueprint\Models\Column;

final class ModelLexerTest extends TestCase
{
    private ModelLexer $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ModelLexer;
    }

    #[Test]
    public function it_returns_nothing_without_models_token(): void
    {
        $this->assertEquals([
            'models' => [],
            'cache' => [],
        ], $this->subject->analyze([]));
    }

    #[Test]
    public function it_returns_models(): void
    {
        $tokens = [
            'models' => [
                'ModelOne' => [
                    'columns' => [
                        'id' => 'id',
                        'name' => 'string nullable',
                    ],
                ],
                'ModelTwo' => [
                    'timestamps' => 'timestamps',
                    'columns' => [
                        'count' => 'integer',
                    ],
                ],
                'ModelThree' => [
                    'columns' => [
                        'id' => 'increments',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(3, $actual['models']);

        $model = $actual['models']['ModelOne'];
        $this->assertEquals('ModelOne', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('name', $columns['name']->name());
        $this->assertEquals('string', $columns['name']->dataType());
        $this->assertEquals(['nullable'], $columns['name']->modifiers());

        $model = $actual['models']['ModelTwo'];
        $this->assertEquals('ModelTwo', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('count', $columns['count']->name());
        $this->assertEquals('integer', $columns['count']->dataType());
        $this->assertEquals([], $columns['count']->modifiers());

        $model = $actual['models']['ModelThree'];
        $this->assertEquals('ModelThree', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(1, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('increments', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
    }

    #[Test]
    public function it_defaults_the_id_column(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'title' => 'string nullable',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->attributes());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('title', $columns['title']->name());
        $this->assertEquals('string', $columns['title']->dataType());
        $this->assertEquals([], $columns['title']->attributes());
        $this->assertEquals(['nullable'], $columns['title']->modifiers());
    }

    #[Test]
    public function it_disables_the_id_column(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'id' => false,
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];

        $this->assertEquals('Model', $model->name());
        $this->assertCount(0, $model->columns());
        $this->assertFalse($model->usesPrimaryKey());
    }

    #[Test]
    public function it_disables_timestamps(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'timestamps' => false,
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertFalse($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());
    }

    #[Test]
    public function it_defaults_to_string_datatype(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'title' => 'nullable',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->attributes());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('title', $columns['title']->name());
        $this->assertEquals('string', $columns['title']->dataType());
        $this->assertEquals([], $columns['title']->attributes());
        $this->assertEquals(['nullable'], $columns['title']->modifiers());
    }

    #[Test]
    public function it_accepts_lowercase_keywords(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'sequence' => 'unsignedbiginteger autoincrement',
                        'content' => 'longtext',
                        'search' => 'fulltext',
                        'saved_at' => 'timestamptz usecurrent',
                        'updated_at' => 'timestamptz usecurrent usecurrentOnUpdate',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(6, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->attributes());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('sequence', $columns['sequence']->name());
        $this->assertEquals('unsignedBigInteger', $columns['sequence']->dataType());
        $this->assertEquals([], $columns['sequence']->attributes());
        $this->assertEquals(['autoIncrement'], $columns['sequence']->modifiers());
        $this->assertEquals('content', $columns['content']->name());
        $this->assertEquals('longText', $columns['content']->dataType());
        $this->assertEquals([], $columns['content']->attributes());
        $this->assertEquals([], $columns['content']->modifiers());
        $this->assertEquals('search', $columns['search']->name());
        $this->assertEquals('fullText', $columns['search']->dataType());
        $this->assertEquals([], $columns['search']->attributes());
        $this->assertEquals([], $columns['search']->modifiers());
        $this->assertEquals('saved_at', $columns['saved_at']->name());
        $this->assertEquals('timestampTz', $columns['saved_at']->dataType());
        $this->assertEquals([], $columns['saved_at']->attributes());
        $this->assertEquals(['useCurrent'], $columns['saved_at']->modifiers());
        $this->assertEquals(['useCurrent', 'useCurrentOnUpdate'], $columns['updated_at']->modifiers());
    }

    #[Test]
    #[DataProvider('dataTypeAttributesDataProvider')]
    public function it_handles_data_type_attributes($definition, $data_type, $attributes): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'column' => $definition,
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('column', $columns['column']->name());
        $this->assertEquals($data_type, $columns['column']->dataType());
        $this->assertEquals($attributes, $columns['column']->attributes());
        $this->assertEquals([], $columns['column']->modifiers());
    }

    #[Test]
    #[DataProvider('modifierAttributesProvider')]
    public function it_handles_modifier_attributes($definition, $modifier, $attributes): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'column' => $definition . ' nullable',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('column', $columns['column']->name());
        $this->assertEquals('string', $columns['column']->dataType());
        $this->assertEquals([], $columns['column']->attributes());
        $this->assertEquals([[$modifier => $attributes], 'nullable'], $columns['column']->modifiers());
    }

    #[Test]
    public function it_handles_attributes_and_modifiers_with_attributes(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'column' => 'string:100 unique charset:utf8',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens)['models']['Model']->columns()['column'];

        $this->assertEquals('column', $actual->name());
        $this->assertEquals('string', $actual->dataType());
        $this->assertEquals(['unique', ['charset' => 'utf8']], $actual->modifiers());
        $this->assertEquals(['100'], $actual->attributes());
    }

    #[Test]
    public function it_enables_soft_deletes(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'softdeletes' => 'softdeletes',
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertTrue($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(1, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
    }

    #[Test]
    public function it_converts_foreign_shorthand_to_id(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'post_id' => 'foreign',
                        'author_id' => 'foreign:user',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(3, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('post_id', $columns['post_id']->name());
        $this->assertEquals('id', $columns['post_id']->dataType());
        $this->assertEquals(['foreign'], $columns['post_id']->modifiers());
        $this->assertEquals('author_id', $columns['author_id']->name());
        $this->assertEquals('id', $columns['author_id']->dataType());
        $this->assertEquals([['foreign' => 'user']], $columns['author_id']->modifiers());
    }

    #[Test]
    public function it_sets_belongs_to_with_foreign_attributes(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'columns' => [
                        'post_id' => 'id foreign',
                        'author_id' => 'id foreign:users',
                        'uid' => 'id:user foreign:users.id',
                        'cntry_id' => 'foreign:countries',
                        'ccid' => 'foreign:countries.code',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(6, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->attributes());
        $this->assertEquals([], $columns['id']->modifiers());

        $this->assertEquals('post_id', $columns['post_id']->name());
        $this->assertEquals('id', $columns['post_id']->dataType());
        $this->assertEquals([], $columns['post_id']->attributes());
        $this->assertEquals(['foreign'], $columns['post_id']->modifiers());

        $this->assertEquals('author_id', $columns['author_id']->name());
        $this->assertEquals('id', $columns['author_id']->dataType());
        $this->assertEquals([], $columns['author_id']->attributes());
        $this->assertEquals([['foreign' => 'users']], $columns['author_id']->modifiers());

        $this->assertEquals('uid', $columns['uid']->name());
        $this->assertEquals('id', $columns['uid']->dataType());
        $this->assertEquals(['user'], $columns['uid']->attributes());
        $this->assertEquals([['foreign' => 'users.id']], $columns['uid']->modifiers());

        $this->assertEquals('cntry_id', $columns['cntry_id']->name());
        $this->assertEquals('id', $columns['cntry_id']->dataType());
        $this->assertEquals([], $columns['cntry_id']->attributes());
        $this->assertEquals([['foreign' => 'countries']], $columns['cntry_id']->modifiers());

        $this->assertEquals('ccid', $columns['ccid']->name());
        $this->assertEquals('id', $columns['ccid']->dataType());
        $this->assertEquals([], $columns['ccid']->attributes());
        $this->assertEquals([['foreign' => 'countries.code']], $columns['ccid']->modifiers());

        $relationships = $model->relationships();
        $this->assertCount(1, $relationships);
        $this->assertEquals([
            'post_id',
            'user:author_id',
            'user:uid',
            'country:cntry_id',
            'country.code:ccid',
        ], $relationships['belongsTo']);
    }

    #[Test]
    public function it_returns_traced_models(): void
    {
        $tokens = [
            'models' => [
                'NewModel' => [
                    'columns' => [
                        'id' => 'id',
                        'name' => 'string nullable',
                    ],
                ],
            ],
            'cache' => [
                'CachedModelOne' => [
                    'timestamps' => 'timestamps',
                    'columns' => [
                        'count' => 'integer',
                    ],
                ],
                'CachedModelTwo' => [
                    'columns' => [
                        'id' => 'id',
                        'name' => 'string nullable',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['NewModel'];
        $this->assertEquals('NewModel', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('name', $columns['name']->name());
        $this->assertEquals('string', $columns['name']->dataType());
        $this->assertEquals(['nullable'], $columns['name']->modifiers());

        $this->assertIsArray($actual['cache']);
        $this->assertCount(2, $actual['cache']);

        $model = $actual['cache']['CachedModelOne'];
        $this->assertEquals('CachedModelOne', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('count', $columns['count']->name());
        $this->assertEquals('integer', $columns['count']->dataType());
        $this->assertEquals([], $columns['count']->modifiers());

        $model = $actual['cache']['CachedModelTwo'];
        $this->assertEquals('CachedModelTwo', $model->name());
        $this->assertTrue($model->usesTimestamps());
        $this->assertFalse($model->usesSoftDeletes());

        $columns = $model->columns();
        $this->assertCount(2, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('name', $columns['name']->name());
        $this->assertEquals('string', $columns['name']->dataType());
        $this->assertEquals(['nullable'], $columns['name']->modifiers());
    }

    #[Test]
    public function it_stores_relationships(): void
    {
        $tokens = [
            'models' => [
                'Subscription' => [
                    'relationships' => [
                        'belongsToMany' => 'Team',
                        'hasmany' => 'Order',
                        'hasOne' => 'Duration, Transaction:tid',
                    ],
                    'columns' => [
                        'different_id' => 'id:user',
                        'title' => 'string',
                        'price' => 'float',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Subscription'];
        $this->assertEquals('Subscription', $model->name());

        $columns = $model->columns();
        $this->assertCount(4, $columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('different_id', $columns);
        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayHasKey('price', $columns);

        $relationships = $model->relationships();
        $this->assertCount(4, $relationships);
        $this->assertEquals(['user:different_id'], $relationships['belongsTo']);
        $this->assertEquals(['Order'], $relationships['hasMany']);
        $this->assertEquals(['Team'], $relationships['belongsToMany']);
        $this->assertEquals(['Duration', 'Transaction:tid'], $relationships['hasOne']);
    }

    #[Test]
    public function it_enables_morphable_and_set_its_reference(): void
    {
        $tokens = [
            'models' => [
                'Model' => [
                    'relationships' => [
                        'morphTo' => 'Morphable',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Model'];
        $this->assertEquals('Model', $model->name());
        $this->assertArrayHasKey('morphTo', $model->relationships());
        $this->assertTrue($model->usesTimestamps());

        $columns = $model->columns();
        $this->assertCount(1, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());

        $relationships = $model->relationships();
        $this->assertCount(1, $relationships);
        $this->assertEquals(['Morphable'], $relationships['morphTo']);
    }

    #[Test]
    public function it_sets_meta_data(): void
    {
        $tokens = [
            'models' => [
                'Post' => [
                    'meta' => [
                        'pivot' => true,
                        'table' => 'post',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Post'];
        $this->assertEquals('Post', $model->name());
        $this->assertSame('post', $model->tableName());
        $this->assertTrue($model->isPivot());
        $this->assertTrue($model->usesTimestamps());

        $columns = $model->columns();
        $this->assertCount(1, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());

        $this->assertCount(0, $model->relationships());
    }

    #[Test]
    public function it_infers_belongsTo_columns(): void
    {
        $tokens = [
            'models' => [
                'Conference' => [
                    'relationships' => [
                        'belongsTo' => 'Venue, Region, \\App\\Models\\User',
                    ],
                    'columns' => [
                        'venue_id' => 'unsigned bigInteger',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Conference'];
        $this->assertEquals('Conference', $model->name());
        $this->assertArrayHasKey('belongsTo', $model->relationships());
        $this->assertTrue($model->usesTimestamps());

        $columns = $model->columns();
        $this->assertCount(4, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('venue_id', $columns['venue_id']->name());
        $this->assertEquals('id', $columns['venue_id']->dataType());
        $this->assertEquals([], $columns['venue_id']->modifiers());
        $this->assertEquals(['Venue'], $columns['venue_id']->attributes());
        $this->assertEquals('region_id', $columns['region_id']->name());
        $this->assertEquals('id', $columns['region_id']->dataType());
        $this->assertEquals([], $columns['region_id']->modifiers());
        $this->assertEquals([], $columns['region_id']->attributes());
        $this->assertEquals('user_id', $columns['user_id']->name());
        $this->assertEquals('id', $columns['user_id']->dataType());
        $this->assertEquals([], $columns['user_id']->modifiers());

        $relationships = $model->relationships();
        $this->assertCount(1, $relationships);
        $this->assertEquals(['Venue', 'Region', '\\App\\Models\\User'], $relationships['belongsTo']);
    }

    #[Test]
    public function it_handles_relationship_aliases(): void
    {
        $tokens = [
            'models' => [
                'Salesman' => [
                    'relationships' => [
                        'belongsTo' => 'User:Lead, Client:Customer',
                    ],
                    'columns' => [
                        'customer_id' => 'id',
                        'company_id' => 'id:Organization',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(1, $actual['models']);

        $model = $actual['models']['Salesman'];
        $this->assertEquals('Salesman', $model->name());
        $this->assertArrayHasKey('belongsTo', $model->relationships());
        $this->assertTrue($model->usesTimestamps());

        $columns = $model->columns();
        $this->assertCount(4, $columns);
        $this->assertEquals('id', $columns['id']->name());
        $this->assertEquals('id', $columns['id']->dataType());
        $this->assertEquals([], $columns['id']->modifiers());
        $this->assertEquals('customer_id', $columns['customer_id']->name());
        $this->assertEquals('id', $columns['customer_id']->dataType());
        $this->assertEquals([], $columns['customer_id']->modifiers());
        $this->assertEquals('company_id', $columns['company_id']->name());
        $this->assertEquals('id', $columns['company_id']->dataType());
        $this->assertEquals([], $columns['company_id']->modifiers());
        $this->assertEquals('lead_id', $columns['lead_id']->name());
        $this->assertEquals('id', $columns['lead_id']->dataType());
        $this->assertEquals([], $columns['lead_id']->modifiers());
        $this->assertEquals(['User'], $columns['lead_id']->attributes());

        $relationships = $model->relationships();
        $this->assertCount(1, $relationships);
        $this->assertEquals(['User:Lead', 'Client:Customer', 'Organization:company_id'], $relationships['belongsTo']);
    }

    /** @test */
    public function it_parses_structured_model_format_with_columns_and_traits(): void
    {
        $tokens = [
            'models' => [
                'User' => [
                    'columns' => [
                        'name' => 'string',
                        'email' => 'string unique',
                        'password' => 'string',
                        'remember_token' => 'rememberToken',
                    ],
                    'traits' => [
                        'HasApiTokens',
                        'Notifiable',
                    ],
                    'relationships' => [
                        'hasMany' => 'Post',
                    ],
                ],
                'Post' => [
                    'columns' => [
                        'title' => 'string:400',
                        'content' => 'longtext',
                        'published_at' => 'nullable timestamp',
                        'author_id' => 'id:user',
                    ],
                    'traits' => [
                        'Searchable',
                    ],
                    'timestamps' => true,
                    'softdeletes' => true,
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $this->assertIsArray($actual['models']);
        $this->assertCount(2, $actual['models']);

        $user = $actual['models']['User'];
        $this->assertEquals('User', $user->name());
        $this->assertTrue($user->hasTraits());
        $this->assertEquals(['HasApiTokens', 'Notifiable'], $user->traits());

        $userColumns = $user->columns();
        $this->assertCount(5, $userColumns); // id, name, email, password, remember_token
        $this->assertEquals('name', $userColumns['name']->name());
        $this->assertEquals('string', $userColumns['name']->dataType());
        $this->assertEquals('email', $userColumns['email']->name());
        $this->assertEquals('string', $userColumns['email']->dataType());
        $this->assertEquals(['unique'], $userColumns['email']->modifiers());

        $post = $actual['models']['Post'];
        $this->assertEquals('Post', $post->name());
        $this->assertTrue($post->hasTraits());
        $this->assertEquals(['Searchable'], $post->traits());
        $this->assertTrue($post->usesSoftDeletes());

        $postColumns = $post->columns();
        $this->assertEquals('title', $postColumns['title']->name());
        $this->assertEquals('string', $postColumns['title']->dataType());
        $this->assertEquals(['400'], $postColumns['title']->attributes());
    }



    /** @test */
    public function it_parses_traits_from_string_format(): void
    {
        // This test simulates how Blueprint's YAML parsing converts trait arrays to strings
        $tokens = [
            'models' => [
                'User' => [
                    'columns' => [
                        'name' => 'string',
                        'email' => 'string unique',
                    ],
                    'traits' => 'HasApiTokens Notifiable', // String format (from YAML processing)
                ],
            ],
        ];

        $actual = $this->subject->analyze($tokens);

        $user = $actual['models']['User'];
        $this->assertEquals('User', $user->name());
        $this->assertTrue($user->hasTraits());
        $this->assertEquals(['HasApiTokens', 'Notifiable'], $user->traits());

        $userColumns = $user->columns();
        $this->assertCount(3, $userColumns); // id, name, email
        $this->assertArrayNotHasKey('traits', $userColumns); // traits should not be a column
    }

    public static function dataTypeAttributesDataProvider(): array
    {
        return [
            ['unsignedDecimal:10,2', 'unsignedDecimal', [10, 2]],
            ['decimal:8,3', 'decimal', [8, 3]],
            ['double:1,4', 'double', [1, 4]],
            ['float:2,10', 'float', [2, 10]],
            ['char:10', 'char', [10]],
            ['string:1000', 'string', [1000]],
            ['enum:one,two,three,four', 'enum', ['one', 'two', 'three', 'four']],
            ['enum:"Jason McCreary",Shift,O\'Doul', 'enum', ['Jason McCreary', 'Shift', 'O\'Doul']],
            ['set:1,2,3,4', 'set', [1, 2, 3, 4]],
        ];
    }

    public static function modifierAttributesProvider(): array
    {
        return [
            ['default:5', 'default', 5],
            ['default:0.00', 'default', 0.00],
            ['default:0', 'default', 0],
            ['default:string', 'default', 'string'],
            ["default:'empty'", 'default', 'empty'],
            ['default:""', 'default', ''],
            ['charset:utf8', 'charset', 'utf8'],
            ['collation:utf8_unicode', 'collation', 'utf8_unicode'],
            ['default:"space between"', 'default', 'space between'],
            ["default:'[]'", 'default', '[]'],
        ];
    }
}

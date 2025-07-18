<?php

namespace Tests\Feature\Generators;

use Blueprint\Blueprint;
use Blueprint\Generators\FactoryGenerator;
use Blueprint\Tree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see FactoryGenerator
 */
final class FactoryGeneratorTest extends TestCase
{
    private $blueprint;

    protected $files;

    /** @var FactoryGenerator */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factoryStub = 'factory.stub';
        $this->subject = new FactoryGenerator($this->files);

        $this->blueprint = new Blueprint;
        $this->blueprint->registerLexer(new \Blueprint\Lexers\ModelLexer);
        $this->blueprint->registerGenerator($this->subject);
    }

    #[Test]
    public function output_writes_nothing_for_empty_tree(): void
    {
        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->shouldNotHaveReceived('put');

        $this->assertGeneratorOutputEquals([], $this->subject->output(new Tree(['models' => []])));
    }

    #[Test]
    #[DataProvider('modelTreeDataProvider')]
    public function output_writes_factory_for_model_tree($definition, $path, $factory): void
    {
        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->andReturn(true);

        $actualContent = null;
        $this->filesystem->expects('put')
            ->with($path, \Mockery::capture($actualContent));

        $tokens = $this->blueprint->parse($this->fixture($definition));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => [$path]], $this->subject->output($tree));
        $this->assertSame($this->fixture($factory), $actualContent);
    }

    #[Test]
    public function output_ignores_nullables_if_fake_nullables_configuration_is_set_to_false(): void
    {
        $this->app['config']->set('blueprint.fake_nullables', false);

        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->with('database/factories')
            ->andReturnTrue();

        $this->filesystem->expects('put')
            ->with('database/factories/PostFactory.php', $this->fixture('factories/fake-nullables.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/readme-example.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['database/factories/PostFactory.php']], $this->subject->output($tree));
    }

    #[Test]
    public function output_generates_references_for_nested_models(): void
    {
        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->times(4)
            ->andReturnTrue();

        $this->filesystem->expects('put')
            ->with('database/factories/QuestionTypeFactory.php', \Mockery::type('string'));
        $this->filesystem->expects('put')
            ->with('database/factories/Appointment/AppointmentTypeFactory.php', \Mockery::type('string'));
        $this->filesystem->expects('put')
            ->with('database/factories/Screening/ReportFactory.php', \Mockery::type('string'));
        $this->filesystem->expects('put')
            ->with('database/factories/Screening/ScreeningQuestionFactory.php', $this->fixture('factories/nested-models.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/nested-models.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals([
            'created' => [
                'database/factories/QuestionTypeFactory.php',
                'database/factories/Appointment/AppointmentTypeFactory.php',
                'database/factories/Screening/ReportFactory.php',
                'database/factories/Screening/ScreeningQuestionFactory.php',
            ],
        ], $this->subject->output($tree));
    }

    #[Test]
    public function output_respects_configuration(): void
    {
        $this->app['config']->set('blueprint.namespace', 'Some\\App');
        $this->app['config']->set('blueprint.models_namespace', 'Models');

        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->with('database/factories')
            ->andReturnTrue();

        $this->filesystem->expects('put')
            ->with('database/factories/PostFactory.php', $this->fixture('factories/post-configured.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/post.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['database/factories/PostFactory.php']], $this->subject->output($tree));
    }

    #[Test]
    public function output_creates_directory_for_nested_components(): void
    {
        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->with('database/factories/Admin')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('database/factories/Admin', 0755, true);

        $this->filesystem->expects('put')
            ->with('database/factories/Admin/UserFactory.php', $this->fixture('factories/nested-components.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/nested-components.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['database/factories/Admin/UserFactory.php']], $this->subject->output($tree));
    }

    #[Test]
    public function output_factory_uses_enum(): void
    {
        $this->filesystem->expects('stub')
            ->with($this->factoryStub)
            ->andReturn($this->stub($this->factoryStub));

        $this->filesystem->expects('exists')
            ->with('database/factories')
            ->andReturnTrue();

        $this->filesystem->expects('put')
            ->with('database/factories/PostFactory.php', $this->fixture('factories/with-enum.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/with-enum.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['database/factories/PostFactory.php']], $this->subject->output($tree));
    }

    public static function modelTreeDataProvider(): array
    {
        return [
            ['drafts/phone.yaml', 'database/factories/PhoneFactory.php', 'factories/phone.php'],
            ['drafts/post.yaml', 'database/factories/PostFactory.php', 'factories/post.php'],
            ['drafts/team.yaml', 'database/factories/TeamFactory.php', 'factories/team.php'],
            ['drafts/unconventional.yaml', 'database/factories/TeamFactory.php', 'factories/unconventional.php'],
            ['drafts/model-modifiers.yaml', 'database/factories/ModifierFactory.php', 'factories/model-modifiers.php'],
            ['drafts/model-key-constraints.yaml', 'database/factories/OrderFactory.php', 'factories/model-key-constraints.php'],
            ['drafts/unconventional-foreign-key.yaml', 'database/factories/StateFactory.php', 'factories/unconventional-foreign-key.php'],
            ['drafts/foreign-key-shorthand.yaml', 'database/factories/CommentFactory.php', 'factories/foreign-key-shorthand.php'],
            ['drafts/resource-statements.yaml', 'database/factories/UserFactory.php', 'factories/resource-statements.php'],
            ['drafts/factory-smallint-and-tinyint.yaml', 'database/factories/ModelFactory.php', 'factories/factory-smallint-and-tinyint.php'],
            ['drafts/all-column-types.yaml', 'database/factories/AllTypeFactory.php', 'factories/all-column-types.php'],
            ['drafts/shorthands.yaml', 'database/factories/NameFactory.php', 'factories/shorthands.php'],
        ];
    }
}

<?php

namespace Tests\Feature\Lexers;

use Blueprint\Blueprint;
use Blueprint\Generators\ControllerGenerator;
use Blueprint\Generators\ModelGenerator;
use Blueprint\Lexers\ConfigLexer;
use Blueprint\Lexers\ControllerLexer;
use Blueprint\Lexers\ModelLexer;
use Blueprint\Lexers\StatementLexer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see ConfigLexer
 */
final class ConfigLexerTest extends TestCase
{
    private $blueprint;

    /**
     * @var ConfigLexer
     */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ConfigLexer;

        $this->modelGenerator = new ModelGenerator($this->filesystem);
        $this->controllerGenerator = new ControllerGenerator($this->filesystem);

        $this->blueprint = new Blueprint;
        $this->blueprint->registerLexer(new ModelLexer);
        $this->blueprint->registerLexer(new ConfigLexer);
        $this->blueprint->registerLexer(new ControllerLexer(new StatementLexer));
        $this->blueprint->registerGenerator($this->modelGenerator);
    }

    #[Test]
    public function it_updates_config(): void
    {
        $tokens = ['config' => ['key' => 'value']];

        $this->subject->analyze($tokens);

        $this->assertSame($tokens['config']['key'], config('blueprint.key'));
    }

    #[Test]
    public function it_uses_app_path_and_namespace_from_inline_configuration(): void
    {
        $this->filesystem->shouldReceive('stub')
            ->with('model.class.stub')
            ->andReturn($this->stub('model.class.stub'));

        $this->filesystem->shouldReceive('stub')
            ->with('model.fillable.stub')
            ->andReturn($this->stub('model.fillable.stub'));

        $this->filesystem->shouldReceive('stub')
            ->with('model.casts.stub')
            ->andReturn($this->stub('model.casts.stub'));

        $this->filesystem->shouldReceive('stub')
            ->with('model.method.stub')
            ->andReturn($this->stub('model.method.stub'));

        $this->filesystem->shouldReceive('exists')
            ->with('atum/Models')
            ->andReturnFalse();
        $this->filesystem->shouldReceive('makeDirectory')
            ->with('atum/Models', 0755, true);
        $this->filesystem->shouldReceive('put')
            ->with('atum/Models/Comment.php', $this->fixture('models/model-configured.php'))
            ->andReturn(true);

        $tokens = $this->blueprint->parse($this->fixture('drafts/relationships-configured.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['atum/Models/Comment.php']], $this->modelGenerator->output($tree));
    }

    #[Test]
    public function it_uses_controller_namespace_config_from_yaml_override(): void
    {
        $this->filesystem->shouldReceive('stub')
            ->with('controller.class.stub')
            ->andReturn($this->stub('controller.class.stub'));
        $this->filesystem->shouldReceive('stub')
            ->with('controller.method.stub')
            ->andReturn($this->stub('controller.method.stub'));

        $this->filesystem->shouldReceive('exists')
            ->with('shift/Other/Http')
            ->andReturnFalse();
        $this->filesystem->shouldReceive('makeDirectory')
            ->with('shift/Other/Http', 0755, true);
        $this->filesystem->shouldReceive('put')
            ->with('shift/Other/Http/UserController.php', $this->fixture('controllers/controller-configured.php'))
            ->andReturn(true);

        $tokens = $this->blueprint->parse($this->fixture('drafts/controller-configured.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['shift/Other/Http/UserController.php']], $this->controllerGenerator->output($tree));
    }
}

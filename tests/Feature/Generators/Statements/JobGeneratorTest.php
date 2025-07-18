<?php

namespace Tests\Feature\Generators\Statements;

use Blueprint\Blueprint;
use Blueprint\Generators\Statements\JobGenerator;
use Blueprint\Lexers\StatementLexer;
use Blueprint\Tree;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see JobGenerator
 */
final class JobGeneratorTest extends TestCase
{
    private $blueprint;

    protected $files;

    /** @var JobGenerator */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new JobGenerator($this->files);

        $this->blueprint = new Blueprint;
        $this->blueprint->registerLexer(new \Blueprint\Lexers\ControllerLexer(new StatementLexer));
        $this->blueprint->registerGenerator($this->subject);
    }

    #[Test]
    public function output_writes_nothing_for_empty_tree(): void
    {
        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));

        $this->filesystem->shouldNotHaveReceived('put');

        $this->assertGeneratorOutputEquals([], $this->subject->output(new Tree(['controllers' => []])));
    }

    #[Test]
    public function output_writes_nothing_tree_without_validate_statements(): void
    {
        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));

        $this->filesystem->shouldNotHaveReceived('put');

        $tokens = $this->blueprint->parse($this->fixture('drafts/render-statements.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals([], $this->subject->output($tree));
    }

    #[Test]
    public function output_writes_jobs(): void
    {
        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));
        $this->filesystem->shouldReceive('stub')
            ->with('constructor.stub')
            ->andReturn($this->stub('constructor.stub'));
        $this->filesystem->shouldReceive('exists')
            ->twice()
            ->with('app/Jobs')
            ->andReturns(false, true);
        $this->filesystem->expects('exists')
            ->with('app/Jobs/CreateUser.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('app/Jobs', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Jobs/CreateUser.php', $this->fixture('jobs/create-user.php'));
        $this->filesystem->expects('exists')
            ->with('app/Jobs/DeleteRole.php')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Jobs/DeleteRole.php', $this->fixture('jobs/delete-user.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/dispatch-statements.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Jobs/CreateUser.php', 'app/Jobs/DeleteRole.php']], $this->subject->output($tree));
    }

    #[Test]
    public function it_only_outputs_new_jobs(): void
    {
        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));
        $this->filesystem->expects('exists')
            ->with('app/Jobs/CreateUser.php')
            ->andReturnTrue();
        $this->filesystem->expects('exists')
            ->with('app/Jobs/DeleteRole.php')
            ->andReturnTrue();

        $tokens = $this->blueprint->parse($this->fixture('drafts/dispatch-statements.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals([], $this->subject->output($tree));
    }

    #[Test]
    public function it_respects_configuration(): void
    {
        $this->app['config']->set('blueprint.namespace', 'Some\\App');
        $this->app['config']->set('blueprint.app_path', 'src/path');

        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));
        $this->filesystem->expects('stub')
            ->with('constructor.stub')
            ->andReturn($this->stub('constructor.stub'));
        $this->filesystem->expects('exists')
            ->with('src/path/Jobs')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('src/path/Jobs/SyncMedia.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('src/path/Jobs', 0755, true);
        $this->filesystem->expects('put')
            ->with('src/path/Jobs/SyncMedia.php', $this->fixture('jobs/job-configured.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/readme-example.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['src/path/Jobs/SyncMedia.php']], $this->subject->output($tree));
    }

    #[Test]
    public function it_respects_configuration_for_property_promotion(): void
    {
        $this->app['config']->set('blueprint.namespace', 'Some\\App');
        $this->app['config']->set('blueprint.app_path', 'src/path');
        $this->app['config']->set('blueprint.property_promotion', true);

        $this->filesystem->expects('stub')
            ->with('job.stub')
            ->andReturn($this->stub('job.stub'));
        $this->filesystem->expects('stub')
            ->with('constructor.stub')
            ->andReturn($this->stub('constructor.stub'));
        $this->filesystem->expects('exists')
            ->with('src/path/Jobs')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('src/path/Jobs/SyncMedia.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('src/path/Jobs', 0755, true);
        $this->filesystem->expects('put')
            ->with('src/path/Jobs/SyncMedia.php', $this->fixture('jobs/job-configured-with-constructor-property-promotion.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/readme-example.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['src/path/Jobs/SyncMedia.php']], $this->subject->output($tree));
    }
}

<?php

namespace Tests\Feature\Generators\Statements;

use Blueprint\Blueprint;
use Blueprint\Generators\Statements\FormRequestGenerator;
use Blueprint\Lexers\StatementLexer;
use Blueprint\Tree;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see FormRequestGenerator
 */
final class FormRequestGeneratorTest extends TestCase
{
    private $blueprint;

    protected $files;

    /** @var FormRequestGenerator */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new FormRequestGenerator($this->files);

        $this->blueprint = new Blueprint;
        $this->blueprint->registerLexer(new \Blueprint\Lexers\ModelLexer);
        $this->blueprint->registerLexer(new \Blueprint\Lexers\ControllerLexer(new StatementLexer));
        $this->blueprint->registerGenerator($this->subject);
    }

    #[Test]
    public function output_writes_nothing_for_empty_tree(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->shouldNotHaveReceived('put');

        $this->assertGeneratorOutputEquals([], $this->subject->output(new Tree(['controllers' => []])));
    }

    #[Test]
    public function output_writes_nothing_without_validate_statements(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->shouldNotHaveReceived('put');

        $tokens = $this->blueprint->parse($this->fixture('drafts/controllers-only.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals([], $this->subject->output($tree));
    }

    #[Test]
    public function output_writes_form_requests(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->shouldReceive('exists')
            ->times(3)
            ->with('app/Http/Requests')
            ->andReturns(false, true, true);
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/PostIndexRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('app/Http/Requests', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/PostIndexRequest.php', $this->fixture('form-requests/post-index.php'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/PostStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/PostStoreRequest.php', $this->fixture('form-requests/post-store.php'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/OtherStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/OtherStoreRequest.php', $this->fixture('form-requests/other-store.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/validate-statements.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Http/Requests/PostIndexRequest.php', 'app/Http/Requests/PostStoreRequest.php', 'app/Http/Requests/OtherStoreRequest.php']], $this->subject->output($tree));
    }

    #[Test]
    public function output_writes_form_requests_with_support_for_model_reference_in_validate_statement(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->shouldReceive('exists')
            ->twice()
            ->with('app/Http/Requests')
            ->andReturns(false, false);

        $this->filesystem->expects('makeDirectory')
            ->twice()
            ->with('app/Http/Requests', 0755, true)
            ->andReturns(true, false);

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/CertificateStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/CertificateStoreRequest.php', $this->fixture('form-requests/certificate-store.php'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/CertificateUpdateRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/CertificateUpdateRequest.php', $this->fixture('form-requests/certificate-update.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/model-reference-validate.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Http/Requests/CertificateStoreRequest.php', 'app/Http/Requests/CertificateUpdateRequest.php']], $this->subject->output($tree));
    }

    #[Test]
    public function it_only_outputs_new_form_requests(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/PostIndexRequest.php')
            ->andReturnTrue();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/PostStoreRequest.php')
            ->andReturnTrue();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/OtherStoreRequest.php')
            ->andReturnTrue();

        $tokens = $this->blueprint->parse($this->fixture('drafts/validate-statements.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals([], $this->subject->output($tree));
    }

    #[Test]
    public function output_supports_nested_form_requests(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/Admin')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/Admin/UserStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('app/Http/Requests/Admin', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/Admin/UserStoreRequest.php', $this->fixture('form-requests/nested-components.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/nested-components.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Http/Requests/Admin/UserStoreRequest.php']], $this->subject->output($tree));
    }

    #[Test]
    public function it_respects_configuration(): void
    {
        $this->app['config']->set('blueprint.namespace', 'Some\\App');
        $this->app['config']->set('blueprint.app_path', 'src/path');

        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->expects('exists')
            ->with('src/path/Http/Requests')
            ->andReturns(false);
        $this->filesystem->expects('exists')
            ->with('src/path/Http/Requests/PostStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('src/path/Http/Requests', 0755, true);
        $this->filesystem->expects('put')
            ->with('src/path/Http/Requests/PostStoreRequest.php', $this->fixture('form-requests/form-request-configured.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/readme-example.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['src/path/Http/Requests/PostStoreRequest.php']], $this->subject->output($tree));
    }

    #[Test]
    public function output_generates_test_for_controller_tree_using_cached_model(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->expects('exists')
            ->with('app/Http/Requests')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/UserStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->with('app/Http/Requests', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/UserStoreRequest.php', $this->fixture('form-requests/cached-model.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/reference-cache.yaml'));
        $modelTokens = [
            'models' => [
                'User' => [
                    'columns' => [
                        'email' => 'string',
                        'password' => 'string',
                    ],
                ],
            ],
        ];
        $tokens['cache'] = $modelTokens['models'];
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Http/Requests/UserStoreRequest.php']], $this->subject->output($tree));
    }

    public function test_output_generates_form_request_without_softdeletes(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));
        $this->filesystem->expects('exists')
            ->twice()
            ->with('app/Http/Requests')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/ProjectStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/ProjectUpdateRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->twice()
            ->with('app/Http/Requests', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/ProjectStoreRequest.php', $this->fixture('form-requests/form-requests-softdeletes.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/form-requests-softdeletes.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        self::assertGeneratorOutputEquals([
            'created' => [
                'app/Http/Requests/ProjectStoreRequest.php',
                'app/Http/Requests/ProjectUpdateRequest.php',
            ],
        ], $this->subject->output($tree));
    }

    public function test_output_generates_form_request_without_softdeletestz(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));
        $this->filesystem->expects('exists')
            ->twice()
            ->with('app/Http/Requests')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/RepoStoreRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('exists')
            ->with('app/Http/Requests/RepoUpdateRequest.php')
            ->andReturnFalse();
        $this->filesystem->expects('makeDirectory')
            ->twice()
            ->with('app/Http/Requests', 0755, true);
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/RepoUpdateRequest.php', $this->fixture('form-requests/form-requests-softdeletestz.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/form-requests-softdeletestz.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        self::assertGeneratorOutputEquals([
            'created' => [
                'app/Http/Requests/RepoStoreRequest.php',
                'app/Http/Requests/RepoUpdateRequest.php',
            ],
        ], $this->subject->output($tree));
    }

    public function test_output_generates_form_request_without_parent_id_column_validation(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));
        $this->filesystem->expects('exists')
            ->twice()
            ->with('app/Http/Requests')
            ->andReturnFalse();
        $this->filesystem->expects('put')
            ->with('app/Http/Requests/CommentStoreRequest.php', $this->fixture('form-requests/form-requests-controller-has-parent.php'));

        $tokens = $this->blueprint->parse($this->fixture('drafts/form-requests-controller-has-parent.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        self::assertGeneratorOutputEquals([
            'created' => [
                'app/Http/Requests/CommentStoreRequest.php',
                'app/Http/Requests/CommentUpdateRequest.php',
            ],
        ], $this->subject->output($tree));
    }

    #[Test]
    public function output_generates_form_request_with_actual_content(): void
    {
        $this->filesystem->expects('stub')
            ->with('request.stub')
            ->andReturn($this->stub('request.stub'));

        $this->filesystem->shouldAllowMockingMethod('put');
        $this->filesystem->expects('put')
            ->times(3)
            ->withArgs(function ($path, $content) {
                file_put_contents('generated-output.php', $content);
                return true;
            });

        $tokens = $this->blueprint->parse($this->fixture('drafts/form-requests-with-actual-content.yaml'));
        $tree = $this->blueprint->analyze($tokens);

        $this->assertGeneratorOutputEquals(['created' => ['app/Http/Requests/PostIndexRequest.php', 'app/Http/Requests/PostStoreRequest.php', 'app/Http/Requests/OtherStoreRequest.php']], $this->subject->output($tree));
    }
}

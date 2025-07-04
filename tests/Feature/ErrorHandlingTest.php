<?php

namespace Tests\Feature;

use Blueprint\Blueprint;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Exceptions\GenerationException;
use Blueprint\Exceptions\BlueprintException;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    private Blueprint $blueprint;
    private Filesystem $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->blueprint = new Blueprint();
        $this->fileSystem = new Filesystem();
    }

    /** @test */
    public function it_throws_parsing_exception_for_invalid_yaml()
    {
        $invalidYaml = "models:\n  User:\n    invalid: [\n      unclosed array";
        
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Failed to parse YAML file');
        
        $this->blueprint->parse($invalidYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_parsing_exception_for_missing_required_sections()
    {
        $emptyYaml = "# Empty YAML file\nconfig:\n  namespace: App";
        
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Missing required section');
        
        $this->blueprint->parse($emptyYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_invalid_model_name()
    {
        $invalidModelYaml = "models:\n  123InvalidName:\n    columns:\n      name: string";
        
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Model name must start with uppercase letter');
        
        $this->blueprint->parse($invalidModelYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_invalid_column_name()
    {
        $invalidColumnYaml = "models:\n  User:\n    columns:\n      123invalid: string";
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Column name must start with lowercase letter');
        
        $this->blueprint->parse($invalidColumnYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_duplicate_model_definitions()
    {
        $duplicateModelYaml = "models:\n  User:\n    columns:\n      name: string\n  User:\n    columns:\n      email: string";
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Duplicate model definition');
        
        $this->blueprint->parse($duplicateModelYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_invalid_relationship_type()
    {
        $invalidRelationshipYaml = "models:\n  User:\n    relationships:\n      posts:\n        invalidRelationType: Post";
        
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Unsupported relationship type');
        
        $this->blueprint->parse($invalidRelationshipYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_invalid_controller_name()
    {
        $invalidControllerYaml = "controllers:\n  123InvalidController:\n    index:\n      query: all";
        
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Controller name must start with uppercase letter');
        
        $this->blueprint->parse($invalidControllerYaml, true, 'test.yaml');
    }

    /** @test */
    public function it_throws_validation_exception_for_invalid_method_name()
    {
        $invalidMethodYaml = "controllers:\n  UserController:\n    123InvalidMethod:\n      query: all";
        
        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Method name \'123InvalidMethod\' must start with lowercase letter');
        
        $this->blueprint->parse($invalidMethodYaml, true, 'test.yaml');
    }

    /** @test */
    public function parsing_exception_includes_helpful_suggestions()
    {
        try {
            $invalidYaml = "models:\n  User:\n    invalid: [\n      unclosed array";
            $this->blueprint->parse($invalidYaml, true, 'test.yaml');
        } catch (ParsingException $e) {
            $suggestions = $e->getSuggestions();
            
            $this->assertNotEmpty($suggestions);
            $this->assertContains('Check for proper YAML indentation (use spaces, not tabs)', $suggestions);
            $this->assertContains('Ensure all strings with special characters are quoted', $suggestions);
            $this->assertEquals('test.yaml', $e->getFilePath());
        }
    }

    /** @test */
    public function validation_exception_includes_context_and_suggestions()
    {
        try {
            $invalidColumnYaml = "models:\n  User:\n    columns:\n      123invalid: string";
            $this->blueprint->parse($invalidColumnYaml, true, 'test.yaml');
        } catch (ValidationException $e) {
            $context = $e->getContext();
            $suggestions = $e->getSuggestions();
            
            $this->assertArrayHasKey('column', $context);
            $this->assertArrayHasKey('model', $context);
            $this->assertEquals('123invalid', $context['column']);
            $this->assertEquals('User', $context['model']);
            
            $this->assertNotEmpty($suggestions);
            $this->assertContains('Check column name follows PHP variable naming conventions', $suggestions);
        }
    }

    /** @test */
    public function blueprint_exception_formats_message_correctly()
    {
        $exception = new BlueprintException(
            'Test error message',
            1000,
            null,
            ['key' => 'value', 'array' => ['item1', 'item2']],
            ['First suggestion', 'Second suggestion']
        );
        
        $exception->setFilePath('test.yaml')->setLineNumber(42);
        
        $formattedMessage = $exception->getFormattedMessage();
        
        $this->assertStringContainsString('Test error message', $formattedMessage);
        $this->assertStringContainsString('File: test.yaml on line 42', $formattedMessage);
        $this->assertStringContainsString('Context:', $formattedMessage);
        $this->assertStringContainsString('key: value', $formattedMessage);
        $this->assertStringContainsString('array: ["item1","item2"]', $formattedMessage);
        $this->assertStringContainsString('Suggestions:', $formattedMessage);
        $this->assertStringContainsString('• First suggestion', $formattedMessage);
        $this->assertStringContainsString('• Second suggestion', $formattedMessage);
    }

    /** @test */
    public function generation_exception_provides_file_write_error_details()
    {
        $exception = GenerationException::fileWriteError('/invalid/path/file.php', 'Permission denied');
        
        $this->assertStringContainsString('Failed to write file', $exception->getMessage());
        $this->assertStringContainsString('/invalid/path/file.php', $exception->getMessage());
        $this->assertEquals('/invalid/path/file.php', $exception->getFilePath());
        
        $suggestions = $exception->getSuggestions();
        $this->assertContains('Check file and directory permissions', $suggestions);
        $this->assertContains('Ensure the target directory exists and is writable', $suggestions);
    }

    /** @test */
    public function generation_exception_provides_template_not_found_error_details()
    {
        $searchPaths = ['/path1/stub.php', '/path2/stub.php'];
        $exception = GenerationException::templateNotFound('missing.stub', $searchPaths);
        
        $this->assertStringContainsString('Template \'missing.stub\' not found', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('missing.stub', $context['template']);
        $this->assertEquals($searchPaths, $context['searchPaths']);
        
        $suggestions = $exception->getSuggestions();
        $this->assertContains('Check if the template file exists in the stubs directory', $suggestions);
        $this->assertContains('Run "php artisan blueprint:stubs" to publish default stubs', $suggestions);
    }

    /** @test */
    public function validation_exception_provides_missing_foreign_key_error_details()
    {
        $exception = ValidationException::missingForeignKey('user_id', 'Post', 'User');
        
        $this->assertStringContainsString('Foreign key \'user_id\' references non-existent model \'User\'', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals('user_id', $context['foreignKey']);
        $this->assertEquals('Post', $context['model']);
        $this->assertEquals('User', $context['referencedModel']);
        
        $suggestions = $exception->getSuggestions();
        $this->assertContains('Define the \'User\' model in your YAML file', $suggestions);
        $this->assertContains('Check the spelling of the referenced model name', $suggestions);
    }

    /** @test */
    public function validation_exception_provides_circular_dependency_error_details()
    {
        $dependencyChain = ['User', 'Post', 'Comment', 'User'];
        $exception = ValidationException::circularDependency($dependencyChain);
        
        $this->assertStringContainsString('Circular dependency detected: User -> Post -> Comment -> User', $exception->getMessage());
        
        $context = $exception->getContext();
        $this->assertEquals($dependencyChain, $context['dependencyChain']);
        
        $suggestions = $exception->getSuggestions();
        $this->assertContains('Review model relationships to eliminate circular references', $suggestions);
        $this->assertContains('Consider using nullable foreign keys to break cycles', $suggestions);
    }
} 
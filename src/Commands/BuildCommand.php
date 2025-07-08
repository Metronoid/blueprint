<?php

namespace Blueprint\Commands;

use Blueprint\Blueprint;
use Blueprint\Builder;
use Blueprint\Exceptions\BlueprintException;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Exceptions\GenerationException;
use Blueprint\ErrorHandling\ErrorHandlingManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class BuildCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blueprint:build
                            {draft? : The path to the draft file, default: draft.yaml or draft.yml }
                            {--only= : Comma separated list of file classes to generate, skipping the rest }
                            {--skip= : Comma separated list of file classes to skip, generating the rest }
                            {--m|overwrite-migrations : Update existing migration files, if found }
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build components from a Blueprint draft';

    protected Filesystem $filesystem;

    private Builder $builder;

    private ErrorHandlingManager $errorHandler;

    public function __construct(Filesystem $filesystem, Builder $builder)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->builder = $builder;
        $this->errorHandler = new ErrorHandlingManager();
    }

    public function handle(): int
    {
        try {
            $file = $this->argument('draft') ?? $this->defaultDraftFile();

            if (!$this->filesystem->exists($file)) {
                $this->error('Draft file could not be found: ' . ($file ?: 'draft.yaml'));
                $this->line('');
                $this->line('Suggestions:');
                $this->line('  • Create a draft.yaml or draft.yml file in your project root');
                $this->line('  • Specify the correct path to your draft file');
                $this->line('  • Run "php artisan blueprint:new" to create a new draft file');

                return 1;
            }

            $only = $this->option('only') ?: '';
            $skip = $this->option('skip') ?: '';
            $overwriteMigrations = $this->option('overwrite-migrations') ?: false;

            $blueprint = resolve(Blueprint::class);
            $generated = $this->builder->execute($blueprint, $this->filesystem, $file, $only, $skip, $overwriteMigrations);

            collect($generated)->each(
                function ($files, $action) {
                    $this->line(Str::studly($action) . ':', $this->outputStyle($action));
                    collect($files)->each(
                        function ($file) {
                            $this->line('- ' . $file);
                        }
                    );

                    $this->line('');
                }
            );

            return 0;
        } catch (ParsingException $e) {
            return $this->handleBlueprintException($e, 'Parsing Error');
        } catch (ValidationException $e) {
            return $this->handleBlueprintException($e, 'Validation Error');
        } catch (GenerationException $e) {
            return $this->handleBlueprintException($e, 'Generation Error');
        } catch (BlueprintException $e) {
            return $this->handleBlueprintException($e, 'Blueprint Error');
        } catch (\Exception $e) {
            $this->error('Unexpected Error: ' . $e->getMessage());
            $this->line('');
            $this->line('This appears to be an unexpected error. Please consider:');
            $this->line('  • Reporting this issue to the Blueprint maintainers');
            $this->line('  • Including your draft file and this error message');
            $this->line('  • Checking if your Laravel and PHP versions are supported');
            
            if ($this->option('verbose')) {
                $this->line('');
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['draft', InputArgument::OPTIONAL, 'The path to the draft file, default: draft.yaml or draft.yml', []],
        ];
    }

    private function outputStyle(string $action): string
    {
        if ($action === 'deleted') {
            return 'error';
        } elseif ($action === 'updated' || $action === 'skipped') {
            return 'comment';
        }

        return 'info';
    }

    private function defaultDraftFile(): string
    {
        return file_exists('draft.yml') ? 'draft.yml' : 'draft.yaml';
    }

    /**
     * Handle Blueprint exceptions with logging and recovery.
     */
    private function handleBlueprintException(BlueprintException $exception, string $errorType): int
    {
        // Use the error handling manager to process the exception
        $result = $this->errorHandler->handleException($exception);
        
        // Display the error
        $this->error($errorType . ': ' . $exception->getMessage());
        
        // Display error ID
        $this->line('');
        $this->line('Error ID: ' . $result->getErrorId());
        
        // Display recovery information if available
        if ($result->hasRecoveryResult()) {
            $this->line('');
            if ($result->isRecoverySuccessful()) {
                $this->line('✅ Recovery: ' . $result->getRecoveryResult()->getMessage(), 'info');
                
                // Show recovery data if available
                $recoveryData = $result->getRecoveryResult()->getData();
                if (!empty($recoveryData)) {
                    foreach ($recoveryData as $key => $value) {
                        if ($key === 'suggestion') {
                            $this->line('  Suggestion: ' . $value);
                        } elseif ($key === 'fixes') {
                            $this->line('  Applied fixes:');
                            foreach ((array) $value as $fix) {
                                $this->line('    • ' . $fix);
                            }
                        }
                    }
                }
            } else {
                $this->line('❌ Recovery failed: ' . $result->getRecoveryResult()->getMessage(), 'comment');
            }
        }
        
        // Display detailed error information
        $this->displayErrorDetails($exception);
        
        return 1;
    }

    /**
     * Display detailed error information for Blueprint exceptions.
     */
    private function displayErrorDetails(BlueprintException $exception): void
    {
        $context = $exception->getContext();
        
        // Show file information
        if (isset($context['file'])) {
            $this->line('');
            $this->line('File: ' . $context['file']);
        }
        
        // Show line number and context for parsing errors
        if (isset($context['line_number']) && isset($context['context_lines'])) {
            $this->line('');
            $this->line('Context:');
            $this->line('');
            
            foreach ($context['context_lines'] as $lineNum => $lineContent) {
                $prefix = $lineNum === $context['line_number'] ? '>>> ' : '    ';
                $this->line($prefix . sprintf('%3d', $lineNum) . ': ' . $lineContent);
            }
            
            $this->line('');
        }
        
        // Show suggestions
        $suggestions = $exception->getSuggestions();
        if (!empty($suggestions)) {
            $this->line('Suggestions:');
            foreach ($suggestions as $suggestion) {
                if ($suggestion) {
                    $this->line('  • ' . $suggestion);
                }
            }
            $this->line('');
        }
        
        // Show recovery information if available
        if (isset($context['fixed_content'])) {
            $this->line('Auto-fixed content:');
            $this->line('');
            $this->line($context['fixed_content']);
            $this->line('');
        }
        
        // Show remaining issues if any
        if (isset($context['remaining_issues'])) {
            $this->line('Remaining issues:');
            foreach ($context['remaining_issues'] as $issue) {
                $this->line('  • ' . $issue);
            }
            $this->line('');
        }
    }

    /**
     * Format a context value for display in console.
     */
    private function formatContextValue(mixed $value): string
    {
        if (is_array($value)) {
            if (count($value) <= 3) {
                return '[' . implode(', ', $value) . ']';
            }
            return '[' . implode(', ', array_slice($value, 0, 3)) . '... +' . (count($value) - 3) . ' more]';
        }

        if (is_object($value)) {
            return get_class($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_null($value)) {
            return 'null';
        }

        $stringValue = (string) $value;
        return strlen($stringValue) > 100 ? substr($stringValue, 0, 100) . '...' : $stringValue;
    }
}

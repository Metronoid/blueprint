<?php

namespace Blueprint\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Blueprint\Exceptions\ParsingException;
use Blueprint\ErrorHandling\RecoveryManager;
use Blueprint\ErrorHandling\ErrorLogger;

class ValidateYamlCommand extends Command
{
    protected $signature = 'blueprint:validate-yaml
                            {file : The path to the YAML file to validate}
                            {--fix : Attempt to automatically fix YAML syntax issues}
                            {--output= : Output file for fixed content}';

    protected $description = 'Validate and optionally fix YAML syntax issues';

    private RecoveryManager $recoveryManager;
    private ErrorLogger $errorLogger;

    public function __construct(RecoveryManager $recoveryManager, ErrorLogger $errorLogger)
    {
        parent::__construct();
        $this->recoveryManager = $recoveryManager;
        $this->errorLogger = $errorLogger;
    }

    public function handle(Filesystem $filesystem): int
    {
        $filePath = $this->argument('file');
        $shouldFix = $this->option('fix');
        $outputFile = $this->option('output');

        if (!$filesystem->exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Validating YAML file: {$filePath}");

        try {
            $content = $filesystem->get($filePath);
            $parsed = Yaml::parse($content);
            
            $this->info('✅ YAML file is valid!');
            $this->displayYamlStructure($parsed);
            
            return 0;
        } catch (ParseException $e) {
            $this->error('❌ YAML parsing error: ' . $e->getMessage());
            
            // Create a ParsingException for recovery
            $parsingException = ParsingException::invalidYaml($filePath, $e->getMessage());
            
            if ($shouldFix) {
                return $this->attemptFix($parsingException, $outputFile);
            } else {
                $this->displayErrorContext($parsingException);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());
            return 1;
        }
    }

    private function attemptFix(ParsingException $exception, ?string $outputFile): int
    {
        $this->line('');
        $this->info('Attempting to fix YAML syntax issues...');
        
        $result = $this->recoveryManager->attemptRecovery($exception);
        
        if ($result->isSuccessful()) {
            $this->info('✅ Successfully fixed YAML syntax issues!');
            
            $data = $result->getData();
            if (isset($data['fixes'])) {
                $this->line('');
                $this->line('Applied fixes:');
                foreach ($data['fixes'] as $fix) {
                    $this->line('  • ' . $fix);
                }
            }
            
            if (isset($data['fixed_content'])) {
                $this->line('');
                $this->info('Fixed content:');
                $this->line('');
                $this->line($data['fixed_content']);
                
                if ($outputFile) {
                    file_put_contents($outputFile, $data['fixed_content']);
                    $this->info("Fixed content saved to: {$outputFile}");
                }
            }
            
            if (isset($data['suggestion'])) {
                $this->line('');
                $this->line('💡 ' . $data['suggestion']);
            }
            
            return 0;
        } else {
            $this->error('❌ Failed to fix YAML syntax issues');
            $this->line('');
            $this->line('Recovery result: ' . $result->getMessage());
            
            $data = $result->getData();
            if (isset($data['remaining_issues'])) {
                $this->line('');
                $this->line('Remaining issues:');
                foreach ($data['remaining_issues'] as $issue) {
                    $this->line('  • ' . $issue);
                }
            }
            
            $this->displayErrorContext($exception);
            return 1;
        }
    }

    private function displayErrorContext(ParsingException $exception): void
    {
        $context = $exception->getContext();
        
        if (isset($context['line_number']) && isset($context['context_lines'])) {
            $this->line('');
            $this->line('Error context:');
            $this->line('');
            
            foreach ($context['context_lines'] as $lineNum => $lineContent) {
                $prefix = $lineNum === $context['line_number'] ? '>>> ' : '    ';
                $this->line($prefix . sprintf('%3d', $lineNum) . ': ' . $lineContent);
            }
        }
        
        $suggestions = $exception->getSuggestions();
        if (!empty($suggestions)) {
            $this->line('');
            $this->line('Suggestions:');
            foreach ($suggestions as $suggestion) {
                if ($suggestion) {
                    $this->line('  • ' . $suggestion);
                }
            }
        }
    }

    private function displayYamlStructure(array $parsed): void
    {
        $this->line('');
        $this->line('YAML structure:');
        $this->displayStructure($parsed, 0);
    }

    private function displayStructure(array $data, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->line($indent . $key . ':');
                $this->displayStructure($value, $depth + 1);
            } else {
                $displayValue = is_string($value) ? '"' . $value . '"' : $value;
                $this->line($indent . $key . ': ' . $displayValue);
            }
        }
    }
} 
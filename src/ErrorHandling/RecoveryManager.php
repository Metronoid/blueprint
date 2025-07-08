<?php

namespace Blueprint\ErrorHandling;

use Blueprint\Exceptions\BlueprintException;
use Blueprint\Exceptions\ParsingException;
use Blueprint\Exceptions\ValidationException;
use Blueprint\Exceptions\GenerationException;

class RecoveryManager
{
    private ErrorLogger $logger;
    private array $strategies = [];
    private int $maxRetries = 3;
    private float $retryDelay = 1.0; // seconds

    public function __construct(ErrorLogger $logger)
    {
        $this->logger = $logger;
        $this->registerDefaultStrategies();
    }

    public function attemptRecovery(BlueprintException $exception): RecoveryResult
    {
        $errorId = $exception->getErrorId();
        $strategies = $this->getStrategiesForException($exception);

        foreach ($strategies as $strategy) {
            $this->logger->logRecoveryAttempt($errorId, $strategy['name'], false, [
                'attempt' => 'starting'
            ]);

            try {
                $result = $this->executeStrategy($strategy, $exception);
                
                if ($result->isSuccessful()) {
                    $this->logger->logRecoveryAttempt($errorId, $strategy['name'], true, [
                        'recovery_data' => $result->getData()
                    ]);
                    return $result;
                }
            } catch (\Exception $e) {
                $this->logger->logRecoveryAttempt($errorId, $strategy['name'], false, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return new RecoveryResult(false, 'No recovery strategies succeeded');
    }

    private function registerDefaultStrategies(): void
    {
        // File permission recovery
        $this->strategies['file_permission'] = [
            'name' => 'file_permission',
            'applies_to' => [GenerationException::class],
            'handler' => function (GenerationException $exception) {
                return $this->recoverFilePermissions($exception);
            }
        ];

        // YAML syntax recovery
        $this->strategies['yaml_syntax'] = [
            'name' => 'yaml_syntax',
            'applies_to' => [ParsingException::class],
            'handler' => function (ParsingException $exception) {
                return $this->recoverYamlSyntax($exception);
            }
        ];

        // Missing directory recovery
        $this->strategies['missing_directory'] = [
            'name' => 'missing_directory',
            'applies_to' => [GenerationException::class],
            'handler' => function (GenerationException $exception) {
                return $this->recoverMissingDirectory($exception);
            }
        ];

        // Template fallback recovery
        $this->strategies['template_fallback'] = [
            'name' => 'template_fallback',
            'applies_to' => [GenerationException::class],
            'handler' => function (GenerationException $exception) {
                return $this->recoverTemplateFallback($exception);
            }
        ];

        // Validation auto-fix
        $this->strategies['validation_autofix'] = [
            'name' => 'validation_autofix',
            'applies_to' => [ValidationException::class],
            'handler' => function (ValidationException $exception) {
                return $this->recoverValidationIssues($exception);
            }
        ];
    }

    private function getStrategiesForException(BlueprintException $exception): array
    {
        $applicableStrategies = [];
        $exceptionClass = get_class($exception);

        foreach ($this->strategies as $strategy) {
            if (in_array($exceptionClass, $strategy['applies_to'])) {
                $applicableStrategies[] = $strategy;
            }
        }

        return $applicableStrategies;
    }

    private function executeStrategy(array $strategy, BlueprintException $exception): RecoveryResult
    {
        $handler = $strategy['handler'];
        return $handler($exception);
    }

    private function recoverFilePermissions(GenerationException $exception): RecoveryResult
    {
        $filePath = $exception->getFilePath();
        $context = $exception->getContext();

        if (!$filePath || !isset($context['permission_error'])) {
            return new RecoveryResult(false, 'Not a permission error');
        }

        // Check if directory exists and is writable
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            return new RecoveryResult(false, 'Directory does not exist');
        }

        if (!is_writable($directory)) {
            return new RecoveryResult(false, 'Directory is not writable', [
                'suggestion' => "Run: chmod 755 {$directory}"
            ]);
        }

        return new RecoveryResult(true, 'Directory permissions are correct');
    }

    private function recoverYamlSyntax(ParsingException $exception): RecoveryResult
    {
        $context = $exception->getContext();
        $filePath = $exception->getFilePath();
        
        // Try to read the actual file content for better analysis
        $yamlContent = null;
        if ($filePath && file_exists($filePath)) {
            $yamlContent = file_get_contents($filePath);
        } elseif (isset($context['yaml_content'])) {
            $yamlContent = $context['yaml_content'];
        }
        
        if (!$yamlContent) {
            return new RecoveryResult(false, 'No YAML content to fix');
        }

        $fixes = [];
        $fixedContent = $yamlContent;

        // Enhanced YAML syntax fixes for dashboard files
        $commonFixes = [
            // Fix missing spaces after colons
            '/(\w+):(\S)/' => '$1: $2',
            // Fix incorrect indentation (basic)
            '/^(\s*)-(\S)/' => '$1- $2',
            // Fix missing quotes around strings with special characters
            '/:\s*([^"\'\s][^:\n]*[!@#$%^&*()][^:\n]*)$/m' => ': "$1"',
            // Fix missing quotes around strings with spaces
            '/:\s*([^"\'\s][^:\n]*\s[^:\n]*)$/m' => ': "$1"',
            // Fix trailing spaces
            '/[ \t]+$/m' => '',
            // Fix mixed indentation (tabs to spaces)
            '/^\t+/m' => function($matches) {
                return str_repeat('  ', strlen($matches[0]));
            },
            // Fix missing colons after keys
            '/^(\s*)(\w+)(\s+)([^:])/m' => '$1$2:$3$4',
            // Fix list items without proper indentation
            '/^(\s*)-(\s*)([^-\s])/m' => '$1- $2$3',
        ];

        foreach ($commonFixes as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $newContent = preg_replace_callback($pattern, $replacement, $fixedContent);
            } else {
                $newContent = preg_replace($pattern, $replacement, $fixedContent);
            }
            
            if ($newContent !== $fixedContent) {
                $fixes[] = "Applied fix: " . $pattern;
                $fixedContent = $newContent;
            }
        }

        // Specific fixes for dashboard YAML structure
        $dashboardFixes = [
            // Fix navigation items structure
            '/^(\s*)navigation:\s*$/m' => '$1navigation:',
            // Fix widget definitions
            '/^(\s*)(\w+):\s*$/m' => '$1$2:',
            // Fix theme color values
            '/:\s*#([0-9a-fA-F]{6})\s*$/m' => ': "#$1"',
            // Fix boolean values
            '/:\s*(true|false)\s*$/m' => ': $1',
            // Fix numeric values
            '/:\s*(\d+)\s*$/m' => ': $1',
        ];

        foreach ($dashboardFixes as $pattern => $replacement) {
            $newContent = preg_replace($pattern, $replacement, $fixedContent);
            if ($newContent !== $fixedContent) {
                $fixes[] = "Applied dashboard fix: " . $pattern;
                $fixedContent = $newContent;
            }
        }

        // Try to validate the fixed content
        try {
            \Symfony\Component\Yaml\Yaml::parse($fixedContent);
            
            if (!empty($fixes)) {
                return new RecoveryResult(true, 'Applied YAML syntax fixes', [
                    'fixes' => $fixes,
                    'fixed_content' => $fixedContent,
                    'suggestion' => 'The YAML file has been automatically fixed. You can now run the command again.'
                ]);
            }
        } catch (\Exception $e) {
            // If the fixed content still doesn't parse, provide more specific suggestions
            $suggestions = $this->analyzeYamlError($fixedContent, $e->getMessage());
            
            return new RecoveryResult(false, 'YAML syntax fixes applied but file still invalid', [
                'fixes' => $fixes,
                'fixed_content' => $fixedContent,
                'remaining_issues' => $suggestions
            ]);
        }

        return new RecoveryResult(false, 'No automatic YAML fixes available');
    }

    private function analyzeYamlError(string $content, string $errorMessage): array
    {
        $suggestions = [];
        
        // Analyze common YAML issues
        if (strpos($errorMessage, 'indentation') !== false) {
            $suggestions[] = 'Check indentation consistency (use spaces, not tabs)';
        }
        
        if (strpos($errorMessage, 'quotes') !== false) {
            $suggestions[] = 'Ensure strings with special characters are properly quoted';
        }
        
        if (strpos($errorMessage, 'colon') !== false) {
            $suggestions[] = 'Check for missing colons after keys';
        }
        
        if (strpos($errorMessage, 'list') !== false) {
            $suggestions[] = 'Verify list items are properly formatted with dashes';
        }
        
        // Count lines to help identify the problematic area
        $lines = explode("\n", $content);
        $suggestions[] = 'Review the YAML structure around the error location';
        
        return $suggestions;
    }

    private function recoverMissingDirectory(GenerationException $exception): RecoveryResult
    {
        $filePath = $exception->getFilePath();
        
        if (!$filePath) {
            return new RecoveryResult(false, 'No file path provided');
        }

        $directory = dirname($filePath);
        
        if (is_dir($directory)) {
            return new RecoveryResult(true, 'Directory already exists');
        }

        // Attempt to create directory
        try {
            if (mkdir($directory, 0755, true)) {
                return new RecoveryResult(true, 'Successfully created directory', [
                    'created_directory' => $directory
                ]);
            }
        } catch (\Exception $e) {
            // Directory creation failed, continue to return failure result
        }

        return new RecoveryResult(false, 'Failed to create directory', [
            'suggestion' => "Run: mkdir -p {$directory}"
        ]);
    }

    private function recoverTemplateFallback(GenerationException $exception): RecoveryResult
    {
        $context = $exception->getContext();
        
        if (!isset($context['template_path'])) {
            return new RecoveryResult(false, 'No template path in context');
        }

        $templatePath = $context['template_path'];
        $fallbackPaths = [
            str_replace('.stub', '.fallback.stub', $templatePath),
            str_replace('.stub', '.default.stub', $templatePath),
            dirname($templatePath) . '/default.stub',
        ];

        foreach ($fallbackPaths as $fallbackPath) {
            if (file_exists($fallbackPath)) {
                return new RecoveryResult(true, 'Found fallback template', [
                    'fallback_template' => $fallbackPath
                ]);
            }
        }

        return new RecoveryResult(false, 'No fallback templates available');
    }

    private function recoverValidationIssues(ValidationException $exception): RecoveryResult
    {
        $context = $exception->getContext();
        $fixes = [];

        // Auto-fix common validation issues
        if (isset($context['invalid_relationship'])) {
            $fixes[] = 'Suggested relationship format fixes';
        }

        if (isset($context['invalid_column_type'])) {
            $fixes[] = 'Suggested column type corrections';
        }

        if (isset($context['circular_dependency'])) {
            $fixes[] = 'Suggested dependency resolution';
        }

        if (!empty($fixes)) {
            return new RecoveryResult(true, 'Validation auto-fixes available', [
                'fixes' => $fixes
            ]);
        }

        return new RecoveryResult(false, 'No automatic validation fixes available');
    }

    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    public function setRetryDelay(float $retryDelay): void
    {
        $this->retryDelay = $retryDelay;
    }
} 
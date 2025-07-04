<?php

namespace Blueprint\Exceptions;

/**
 * Exception thrown when file generation fails.
 * 
 * Provides specific suggestions for common file generation issues.
 */
class GenerationException extends BlueprintException
{
    public static function fileWriteError(string $filePath, string $reason): self
    {
        $exception = new self(
            "Failed to write file '{$filePath}': {$reason}",
            2001,
            null,
            ['file' => $filePath, 'reason' => $reason],
            [
                'Check file and directory permissions',
                'Ensure the target directory exists and is writable',
                'Verify there is sufficient disk space',
                'Check if the file is currently open in another application'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function directoryCreateError(string $directoryPath, string $reason): self
    {
        $exception = new self(
            "Failed to create directory '{$directoryPath}': {$reason}",
            2002,
            null,
            ['directory' => $directoryPath, 'reason' => $reason],
            [
                'Check parent directory permissions',
                'Verify the path is valid and accessible',
                'Ensure you have write permissions to the parent directory',
                'Check if the directory already exists with different permissions'
            ]
        );

        return $exception->setFilePath($directoryPath);
    }

    public static function templateNotFound(string $templateName, array $searchPaths): self
    {
        $exception = new self(
            "Template '{$templateName}' not found",
            2003,
            null,
            ['template' => $templateName, 'searchPaths' => $searchPaths],
            [
                'Check if the template file exists in the stubs directory',
                'Verify the template name is spelled correctly',
                'Run "php artisan blueprint:stubs" to publish default stubs',
                'Check custom stub paths in blueprint configuration'
            ]
        );

        return $exception;
    }

    public static function invalidStubContent(string $stubPath, string $reason): self
    {
        $exception = new self(
            "Invalid stub content in '{$stubPath}': {$reason}",
            2004,
            null,
            ['stub' => $stubPath, 'reason' => $reason],
            [
                'Check the stub file for syntax errors',
                'Verify placeholder syntax is correct',
                'Ensure the stub file is properly formatted',
                'Compare with default stubs for reference'
            ]
        );

        return $exception->setFilePath($stubPath);
    }

    public static function modelNotFound(string $modelName, string $context): self
    {
        $exception = new self(
            "Model '{$modelName}' not found in context '{$context}'",
            2005,
            null,
            ['model' => $modelName, 'context' => $context],
            [
                'Ensure the model is defined in your YAML file',
                'Check the model name spelling and case',
                'Verify the model exists in the models section',
                'Run "php artisan blueprint:trace" to include existing models'
            ]
        );

        return $exception;
    }

    public static function conflictingFile(string $filePath, string $action): self
    {
        $exception = new self(
            "File conflict detected for '{$filePath}' during {$action}",
            2006,
            null,
            ['file' => $filePath, 'action' => $action],
            [
                'Use the --force flag to overwrite existing files',
                'Rename the existing file to preserve it',
                'Review the existing file content before overwriting',
                'Consider using version control to track changes'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function invalidNamespace(string $namespace, string $reason): self
    {
        $exception = new self(
            "Invalid namespace '{$namespace}': {$reason}",
            2007,
            null,
            ['namespace' => $namespace, 'reason' => $reason],
            [
                'Ensure namespace follows PSR-4 conventions',
                'Check that namespace matches directory structure',
                'Verify namespace configuration in blueprint.php',
                'Use proper PHP namespace syntax'
            ]
        );

        return $exception;
    }
} 
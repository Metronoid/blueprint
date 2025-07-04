<?php

namespace Blueprint\Exceptions;

/**
 * Exception thrown when YAML parsing fails.
 * 
 * Provides specific suggestions for common YAML syntax errors.
 */
class ParsingException extends BlueprintException
{
    public static function invalidYaml(string $filePath, string $originalMessage): self
    {
        $exception = new self(
            "Failed to parse YAML file: {$originalMessage}",
            1001,
            null,
            ['file' => $filePath],
            [
                'Check for proper YAML indentation (use spaces, not tabs)',
                'Ensure all strings with special characters are quoted',
                'Verify that lists and objects are properly formatted',
                'Check for missing colons after keys',
                'Validate that multiline strings use proper YAML syntax'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function missingRequiredSection(string $section, string $filePath): self
    {
        $exception = new self(
            "Missing required section '{$section}' in YAML file",
            1002,
            null,
            ['section' => $section, 'file' => $filePath],
            [
                "Add the '{$section}' section to your YAML file",
                'Refer to the Blueprint documentation for proper file structure',
                'Check example YAML files in the tests/fixtures directory'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function invalidModelDefinition(string $modelName, string $reason, string $filePath): self
    {
        $exception = new self(
            "Invalid model definition for '{$modelName}': {$reason}",
            1003,
            null,
            ['model' => $modelName, 'reason' => $reason, 'file' => $filePath],
            [
                'Ensure model names are valid PHP class names',
                'Check that all column types are supported',
                'Verify relationship definitions are properly formatted',
                'Validate that foreign key references exist'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function invalidControllerDefinition(string $controllerName, string $reason, string $filePath): self
    {
        $exception = new self(
            "Invalid controller definition for '{$controllerName}': {$reason}",
            1004,
            null,
            ['controller' => $controllerName, 'reason' => $reason, 'file' => $filePath],
            [
                'Ensure controller names are valid PHP class names',
                'Check that all method definitions are properly formatted',
                'Verify that referenced models exist',
                'Validate statement syntax in controller methods'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function unsupportedColumnType(string $columnType, string $modelName, string $filePath): self
    {
        $supportedTypes = [
            'string', 'text', 'integer', 'bigInteger', 'boolean', 'date', 'datetime', 
            'timestamp', 'decimal', 'float', 'json', 'uuid', 'id', 'foreignId'
        ];

        $exception = new self(
            "Unsupported column type '{$columnType}' in model '{$modelName}'",
            1005,
            null,
            ['columnType' => $columnType, 'model' => $modelName, 'supportedTypes' => $supportedTypes],
            [
                'Use one of the supported column types: ' . implode(', ', $supportedTypes),
                'Check the Laravel migration documentation for available column types',
                'Consider using a similar supported type instead'
            ]
        );

        return $exception->setFilePath($filePath);
    }
} 
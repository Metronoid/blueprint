<?php

namespace Blueprint\Exceptions;

/**
 * Exception thrown when validation fails.
 * 
 * Provides specific suggestions for common validation issues.
 */
class ValidationException extends BlueprintException
{
    public static function invalidRelationship(string $relationshipType, string $modelName, string $reason): self
    {
        $exception = new self(
            "Invalid {$relationshipType} relationship in model '{$modelName}': {$reason}",
            3001,
            null,
            ['relationshipType' => $relationshipType, 'model' => $modelName, 'reason' => $reason],
            [
                'Check that the related model exists',
                'Verify the relationship type is supported (hasOne, hasMany, belongsTo, belongsToMany)',
                'Ensure foreign key references are correct',
                'Check pivot table definitions for many-to-many relationships'
            ]
        );

        return $exception;
    }

    public static function invalidColumnDefinition(string $columnName, string $modelName, string $reason): self
    {
        $exception = new self(
            "Invalid column definition '{$columnName}' in model '{$modelName}': {$reason}",
            3002,
            null,
            ['column' => $columnName, 'model' => $modelName, 'reason' => $reason],
            [
                'Check column name follows PHP variable naming conventions',
                'Verify column type is supported',
                'Ensure column modifiers are valid',
                'Check for duplicate column names'
            ]
        );

        return $exception;
    }

    public static function missingForeignKey(string $foreignKey, string $modelName, string $referencedModel): self
    {
        $exception = new self(
            "Foreign key '{$foreignKey}' references non-existent model '{$referencedModel}' in model '{$modelName}'",
            3003,
            null,
            ['foreignKey' => $foreignKey, 'model' => $modelName, 'referencedModel' => $referencedModel],
            [
                "Define the '{$referencedModel}' model in your YAML file",
                'Check the spelling of the referenced model name',
                'Verify the foreign key column name is correct',
                'Ensure the referenced model has a primary key'
            ]
        );

        return $exception;
    }

    public static function invalidMethodStatement(string $methodName, string $controllerName, string $statement, string $reason): self
    {
        $exception = new self(
            "Invalid statement '{$statement}' in method '{$methodName}' of controller '{$controllerName}': {$reason}",
            3004,
            null,
            ['method' => $methodName, 'controller' => $controllerName, 'statement' => $statement, 'reason' => $reason],
            [
                'Check statement syntax according to Blueprint documentation',
                'Verify referenced models and variables exist',
                'Ensure statement type is supported',
                'Check for proper parameter formatting'
            ]
        );

        return $exception;
    }

    public static function duplicateDefinition(string $type, string $name, string $filePath): self
    {
        $exception = new self(
            "Duplicate {$type} definition '{$name}' found",
            3005,
            null,
            ['type' => $type, 'name' => $name, 'file' => $filePath],
            [
                "Remove or rename the duplicate {$type} definition",
                'Check for case-sensitive naming conflicts',
                'Verify unique naming across all definitions',
                'Consider using namespaces to avoid conflicts'
            ]
        );

        return $exception->setFilePath($filePath);
    }

    public static function circularDependency(array $dependencyChain): self
    {
        $chain = implode(' -> ', $dependencyChain);
        
        $exception = new self(
            "Circular dependency detected: {$chain}",
            3006,
            null,
            ['dependencyChain' => $dependencyChain],
            [
                'Review model relationships to eliminate circular references',
                'Consider using nullable foreign keys to break cycles',
                'Restructure relationships to avoid circular dependencies',
                'Use intermediate models if necessary'
            ]
        );

        return $exception;
    }

    public static function invalidConfiguration(string $key, mixed $value, string $reason): self
    {
        $exception = new self(
            "Invalid configuration for '{$key}': {$reason}",
            3007,
            null,
            ['configKey' => $key, 'configValue' => $value, 'reason' => $reason],
            [
                'Check the blueprint configuration file',
                'Verify configuration values match expected types',
                'Refer to the documentation for valid configuration options',
                'Reset to default values if unsure'
            ]
        );

        return $exception;
    }

    public static function incompatibleVersion(string $requiredVersion, string $currentVersion): self
    {
        $exception = new self(
            "Incompatible version: requires {$requiredVersion}, current version is {$currentVersion}",
            3008,
            null,
            ['requiredVersion' => $requiredVersion, 'currentVersion' => $currentVersion],
            [
                'Update Blueprint to the required version',
                'Check Laravel version compatibility',
                'Review changelog for breaking changes',
                'Consider using a compatible version'
            ]
        );

        return $exception;
    }

    public static function invalidModelFormat(string $modelName): self
    {
        $exception = new self(
            "Model '{$modelName}' must use structured format with 'columns' key. Legacy format is no longer supported.",
            3009,
            null,
            ['model' => $modelName],
            [
                "Add a 'columns:' key to your model definition",
                'Move all column definitions under the columns key',
                'Update model-level properties (id, timestamps, etc.) to be at the root level',
                'Refer to the documentation for structured format examples'
            ]
        );

        return $exception;
    }
} 
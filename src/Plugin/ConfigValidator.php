<?php

namespace Blueprint\Plugin;

use Blueprint\Exceptions\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ConfigValidator
{
    protected array $schemas = [];
    protected array $validationRules = [];

    /**
     * Register a configuration schema for a plugin.
     */
    public function registerSchema(string $pluginName, array $schema): void
    {
        $this->schemas[$pluginName] = $schema;
    }

    /**
     * Validate plugin configuration against its schema.
     */
    public function validate(string $pluginName, array $config): array
    {
        if (!isset($this->schemas[$pluginName])) {
            // No schema registered, return config as-is
            return $config;
        }

        $schema = $this->schemas[$pluginName];
        $errors = [];
        
        // Validate required fields
        $this->validateRequired($schema, $config, $errors);
        
        // Validate field types and constraints
        $this->validateFields($schema, $config, $errors);
        
        // Apply default values
        $config = $this->applyDefaults($schema, $config);
        
        if (!empty($errors)) {
            throw new ValidationException(
                "Plugin '{$pluginName}' configuration validation failed:\n" . implode("\n", $errors)
            );
        }
        
        return $config;
    }

    /**
     * Get schema for a plugin.
     */
    public function getSchema(string $pluginName): ?array
    {
        return $this->schemas[$pluginName] ?? null;
    }

    /**
     * Check if a plugin has a registered schema.
     */
    public function hasSchema(string $pluginName): bool
    {
        return isset($this->schemas[$pluginName]);
    }

    /**
     * Get all registered schemas.
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Validate required fields.
     */
    protected function validateRequired(array $schema, array $config, array &$errors): void
    {
        if (!isset($schema['properties'])) {
            return;
        }

        $required = $schema['required'] ?? [];
        
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }
    }

    /**
     * Validate field types and constraints.
     */
    protected function validateFields(array $schema, array $config, array &$errors): void
    {
        if (!isset($schema['properties'])) {
            return;
        }

        foreach ($schema['properties'] as $field => $fieldSchema) {
            if (!isset($config[$field])) {
                continue;
            }

            $value = $config[$field];
            $this->validateField($field, $value, $fieldSchema, $errors);
        }
    }

    /**
     * Validate a single field.
     */
    protected function validateField(string $field, $value, array $fieldSchema, array &$errors): void
    {
        // Type validation
        if (isset($fieldSchema['type'])) {
            if (!$this->validateType($value, $fieldSchema['type'])) {
                $errors[] = "Field '{$field}' must be of type '{$fieldSchema['type']}'";
                return;
            }
        }

        // String validations
        if (is_string($value)) {
            $this->validateString($field, $value, $fieldSchema, $errors);
        }

        // Array validations
        if (is_array($value)) {
            $this->validateArray($field, $value, $fieldSchema, $errors);
        }

        // Numeric validations
        if (is_numeric($value)) {
            $this->validateNumeric($field, $value, $fieldSchema, $errors);
        }

        // Enum validation
        if (isset($fieldSchema['enum'])) {
            if (!in_array($value, $fieldSchema['enum'])) {
                $errors[] = "Field '{$field}' must be one of: " . implode(', ', $fieldSchema['enum']);
            }
        }

        // Pattern validation
        if (isset($fieldSchema['pattern']) && is_string($value)) {
            if (!preg_match($fieldSchema['pattern'], $value)) {
                $errors[] = "Field '{$field}' does not match required pattern";
            }
        }

        // Custom validation
        if (isset($fieldSchema['validate']) && is_callable($fieldSchema['validate'])) {
            $result = $fieldSchema['validate']($value);
            if ($result !== true) {
                $errors[] = "Field '{$field}' validation failed: " . $result;
            }
        }
    }

    /**
     * Validate type.
     */
    protected function validateType($value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer', 'int' => is_int($value),
            'number', 'float' => is_numeric($value),
            'boolean', 'bool' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            'null' => is_null($value),
            default => true,
        };
    }

    /**
     * Validate string constraints.
     */
    protected function validateString(string $field, string $value, array $fieldSchema, array &$errors): void
    {
        if (isset($fieldSchema['minLength']) && strlen($value) < $fieldSchema['minLength']) {
            $errors[] = "Field '{$field}' must be at least {$fieldSchema['minLength']} characters long";
        }

        if (isset($fieldSchema['maxLength']) && strlen($value) > $fieldSchema['maxLength']) {
            $errors[] = "Field '{$field}' must not exceed {$fieldSchema['maxLength']} characters";
        }
    }

    /**
     * Validate array constraints.
     */
    protected function validateArray(string $field, array $value, array $fieldSchema, array &$errors): void
    {
        if (isset($fieldSchema['minItems']) && count($value) < $fieldSchema['minItems']) {
            $errors[] = "Field '{$field}' must have at least {$fieldSchema['minItems']} items";
        }

        if (isset($fieldSchema['maxItems']) && count($value) > $fieldSchema['maxItems']) {
            $errors[] = "Field '{$field}' must not have more than {$fieldSchema['maxItems']} items";
        }

        if (isset($fieldSchema['uniqueItems']) && $fieldSchema['uniqueItems']) {
            if (count($value) !== count(array_unique($value))) {
                $errors[] = "Field '{$field}' must contain unique items";
            }
        }

        // Validate array items
        if (isset($fieldSchema['items'])) {
            foreach ($value as $index => $item) {
                $this->validateField("{$field}[{$index}]", $item, $fieldSchema['items'], $errors);
            }
        }
    }

    /**
     * Validate numeric constraints.
     */
    protected function validateNumeric(string $field, $value, array $fieldSchema, array &$errors): void
    {
        if (isset($fieldSchema['minimum']) && $value < $fieldSchema['minimum']) {
            $errors[] = "Field '{$field}' must be at least {$fieldSchema['minimum']}";
        }

        if (isset($fieldSchema['maximum']) && $value > $fieldSchema['maximum']) {
            $errors[] = "Field '{$field}' must not exceed {$fieldSchema['maximum']}";
        }

        if (isset($fieldSchema['multipleOf']) && $value % $fieldSchema['multipleOf'] !== 0) {
            $errors[] = "Field '{$field}' must be a multiple of {$fieldSchema['multipleOf']}";
        }
    }

    /**
     * Apply default values to configuration.
     */
    protected function applyDefaults(array $schema, array $config): array
    {
        if (!isset($schema['properties'])) {
            return $config;
        }

        foreach ($schema['properties'] as $field => $fieldSchema) {
            if (!isset($config[$field]) && isset($fieldSchema['default'])) {
                $config[$field] = $fieldSchema['default'];
            }
        }

        return $config;
    }

    /**
     * Create a simple schema builder.
     */
    public static function schema(): SchemaBuilder
    {
        return new SchemaBuilder();
    }
}

/**
 * Helper class for building configuration schemas.
 */
class SchemaBuilder
{
    protected array $schema = [
        'type' => 'object',
        'properties' => [],
        'required' => [],
    ];

    /**
     * Add a string field.
     */
    public function string(string $name, array $options = []): self
    {
        $this->schema['properties'][$name] = array_merge([
            'type' => 'string',
        ], $options);

        if ($options['required'] ?? false) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * Add an integer field.
     */
    public function integer(string $name, array $options = []): self
    {
        $this->schema['properties'][$name] = array_merge([
            'type' => 'integer',
        ], $options);

        if ($options['required'] ?? false) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * Add a boolean field.
     */
    public function boolean(string $name, array $options = []): self
    {
        $this->schema['properties'][$name] = array_merge([
            'type' => 'boolean',
        ], $options);

        if ($options['required'] ?? false) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * Add an array field.
     */
    public function array(string $name, array $options = []): self
    {
        $this->schema['properties'][$name] = array_merge([
            'type' => 'array',
        ], $options);

        if ($options['required'] ?? false) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * Add an enum field.
     */
    public function enum(string $name, array $values, array $options = []): self
    {
        $this->schema['properties'][$name] = array_merge([
            'enum' => $values,
        ], $options);

        if ($options['required'] ?? false) {
            $this->schema['required'][] = $name;
        }

        return $this;
    }

    /**
     * Build the schema.
     */
    public function build(): array
    {
        return $this->schema;
    }
} 
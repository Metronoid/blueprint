<?php

namespace BlueprintExtensions\Constraints\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Model;

class ConstraintsLexer implements Lexer
{
    /**
     * Parse constraints configuration from the tokens and add to the tree.
     *
     * @param array $tokens The parsed YAML tokens
     * @return array The modified tree with constraints data
     */
    public function analyze(array $tokens): array
    {
        $tree = [];

        if (isset($tokens['models'])) {
            foreach ($tokens['models'] as $name => $definition) {
                $modelConstraints = [];
                
                // Handle structured format: check if 'columns' key exists
                $hasStructuredFormat = isset($definition['columns']) && is_array($definition['columns']);
                
                if ($hasStructuredFormat) {
                    // New structured format with columns: key
                    $columns = $definition['columns'];
                    
                    // Check if model has constraints defined at model level
                    if (isset($definition['constraints'])) {
                        $modelConstraints = $this->parseModelConstraints($definition['constraints']);
                    }
                    
                    // Parse column-level constraints from the columns section and merge with model-level constraints
                    foreach ($columns as $column => $columnDefinition) {
                        if (is_string($columnDefinition) && $this->hasConstraints($columnDefinition)) {
                            $columnConstraints = $this->parseColumnConstraints($column, $columnDefinition);
                            if (!empty($columnConstraints)) {
                                // Merge with existing model-level constraints for this column
                                if (isset($modelConstraints['columns'][$column])) {
                                    $modelConstraints['columns'][$column] = array_merge(
                                        $modelConstraints['columns'][$column],
                                        $columnConstraints
                                    );
                                } else {
                                    $modelConstraints['columns'][$column] = $columnConstraints;
                                }
                            }
                        }
                    }
                } else {
                    // Legacy format: backward compatibility where everything is at the root level
                    
                    // Check if model has constraints defined at model level
                    if (isset($definition['constraints'])) {
                        $modelConstraints = $this->parseModelConstraints($definition['constraints']);
                    }

                    // Parse column-level constraints from root level definitions and merge with model-level constraints
                    foreach ($definition as $column => $columnDefinition) {
                        // Skip the constraints key as it's already processed
                        if ($column === 'constraints') {
                            continue;
                        }
                        
                        if (is_string($columnDefinition) && $this->hasConstraints($columnDefinition)) {
                            $columnConstraints = $this->parseColumnConstraints($column, $columnDefinition);
                            if (!empty($columnConstraints)) {
                                // Merge with existing model-level constraints for this column
                                if (isset($modelConstraints['columns'][$column])) {
                                    $modelConstraints['columns'][$column] = array_merge(
                                        $modelConstraints['columns'][$column],
                                        $columnConstraints
                                    );
                                } else {
                                    $modelConstraints['columns'][$column] = $columnConstraints;
                                }
                            }
                        }
                    }
                }

                if (!empty($modelConstraints)) {
                    $tree['constraints'][$name] = $modelConstraints;
                }
            }
        }

        return $tree;
    }

    /**
     * Parse model-level constraints configuration.
     *
     * @param mixed $config The constraints configuration
     * @return array The parsed constraints configuration
     */
    private function parseModelConstraints($config): array
    {
        $constraints = [];

        if (is_array($config)) {
            foreach ($config as $column => $columnConstraints) {
                $constraints['columns'][$column] = $this->parseConstraintDefinition($columnConstraints);
            }
        }

        return $constraints;
    }

    /**
     * Check if a column definition has constraints.
     *
     * @param string $definition The column definition
     * @return bool
     */
    private function hasConstraints(string $definition): bool
    {
        $constraintKeywords = [
            'min:', 'max:', 'between:', 'in:', 'not_in:', 'regex:', 'length:',
            'digits:', 'alpha', 'alpha_num', 'email', 'url', 'ip', 'json', 'uuid',
            'before:', 'after:', 'confirmed', 'same:', 'different:'
        ];

        foreach ($constraintKeywords as $keyword) {
            if (str_contains($definition, $keyword)) {
                return true;
            }
        }

        // Special case for 'date': only treat as constraint if it's not the first word (not a data type)
        $parts = explode(' ', trim($definition));
        if (count($parts) > 1 && in_array('date', array_slice($parts, 1))) {
            return true;
        }

        return false;
    }

    /**
     * Parse constraints from a column definition.
     *
     * @param string $column The column name
     * @param string $definition The column definition
     * @return array The parsed constraints
     */
    private function parseColumnConstraints(string $column, string $definition): array
    {
        $constraints = [];
        
        // Handle regex patterns that might contain spaces
        if (preg_match('/regex:([^\s]+(?:\s[^\s]+)*)/', $definition, $matches)) {
            $regexPattern = $matches[1];
            $constraints[] = ['type' => 'regex', 'pattern' => $regexPattern];
            // Remove the regex part from definition for further processing
            $definition = preg_replace('/regex:' . preg_quote($regexPattern, '/') . '/', '', $definition);
        }
        
        $parts = explode(' ', $definition);

        foreach ($parts as $index => $part) {
            if ($this->isConstraintPart($part, $parts, $index)) {
                $constraint = $this->parseConstraintPart($part);
                if ($constraint) {
                    $constraints[] = $constraint;
                }
            }
        }

        return $constraints;
    }

    /**
     * Check if a part is a constraint definition.
     *
     * @param string $part The part to check
     * @param array $allParts All parts of the definition (for context)
     * @param int $index The index of the current part
     * @return bool
     */
    private function isConstraintPart(string $part, array $allParts = [], int $index = 0): bool
    {
        $constraintKeywords = [
            'min:', 'max:', 'between:', 'in:', 'not_in:', 'regex:', 'length:',
            'digits:', 'alpha', 'alpha_num', 'email', 'url', 'ip', 'json', 'uuid',
            'before:', 'after:', 'confirmed', 'same:', 'different:'
        ];

        foreach ($constraintKeywords as $keyword) {
            if (str_starts_with($part, $keyword) || $part === trim($keyword, ':')) {
                return true;
            }
        }

        // Special case: 'date' is only a constraint if it's not the first part (not a data type)
        if ($part === 'date' && $index > 0) {
            return true;
        }

        return false;
    }

    /**
     * Parse a single constraint part.
     *
     * @param string $part The constraint part
     * @return array|null The parsed constraint
     */
    private function parseConstraintPart(string $part): ?array
    {
        if (str_contains($part, ':')) {
            [$type, $value] = explode(':', $part, 2);
            return $this->parseConstraintWithValue($type, $value);
        }

        // Handle constraints without values (like 'alpha', 'email', etc.)
        return $this->parseSimpleConstraint($part);
    }

    /**
     * Parse a constraint with a value.
     *
     * @param string $type The constraint type
     * @param string $value The constraint value
     * @return array The parsed constraint
     */
    private function parseConstraintWithValue(string $type, string $value): array
    {
        $constraint = ['type' => $type];

        switch ($type) {
            case 'min':
            case 'max':
            case 'length':
            case 'digits':
                $constraint['value'] = is_numeric($value) ? (float)$value : $value;
                break;

            case 'between':
                $values = explode(',', $value);
                $constraint['min'] = is_numeric($values[0]) ? (float)$values[0] : $values[0];
                $constraint['max'] = is_numeric($values[1] ?? $values[0]) ? (float)($values[1] ?? $values[0]) : ($values[1] ?? $values[0]);
                break;

            case 'in':
            case 'not_in':
                $constraint['values'] = array_map('trim', explode(',', $value));
                break;

            case 'regex':
                $constraint['pattern'] = $value;
                break;

            case 'before':
            case 'after':
                $constraint['date'] = $value;
                break;

            case 'same':
            case 'different':
                $constraint['field'] = $value;
                break;

            default:
                $constraint['value'] = $value;
        }

        return $constraint;
    }

    /**
     * Parse a simple constraint without a value.
     *
     * @param string $type The constraint type
     * @return array The parsed constraint
     */
    private function parseSimpleConstraint(string $type): array
    {
        return ['type' => $type];
    }

    /**
     * Parse a constraint definition (array format).
     *
     * @param mixed $definition The constraint definition
     * @return array The parsed constraints
     */
    private function parseConstraintDefinition($definition): array
    {
        if (is_string($definition)) {
            return [$this->parseConstraintPart($definition)];
        }

        if (is_array($definition)) {
            $constraints = [];
            foreach ($definition as $constraint) {
                if (is_string($constraint)) {
                    $parsed = $this->parseConstraintPart($constraint);
                    if ($parsed) {
                        $constraints[] = $parsed;
                    }
                } elseif (is_array($constraint)) {
                    $constraints[] = $constraint;
                }
            }
            return $constraints;
        }

        return [];
    }
} 
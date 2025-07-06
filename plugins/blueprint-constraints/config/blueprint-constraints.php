<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Constraint Generation Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how constraints are generated and applied.
    |
    */

    'generate_database_constraints' => true,
    'generate_validation_rules' => true,
    'generate_model_mutators' => false,

    /*
    |--------------------------------------------------------------------------
    | Supported Constraint Types
    |--------------------------------------------------------------------------
    |
    | Define which constraint types are supported by this extension.
    |
    */

    'supported_constraints' => [
        'min' => 'Minimum value constraint',
        'max' => 'Maximum value constraint',
        'between' => 'Value must be between two values',
        'in' => 'Value must be in a list of allowed values',
        'not_in' => 'Value must not be in a list of forbidden values',
        'regex' => 'Value must match a regular expression',
        'length' => 'String length constraints',
        'digits' => 'Numeric digit constraints',
        'alpha' => 'Alphabetic characters only',
        'alpha_num' => 'Alphanumeric characters only',
        'email' => 'Valid email format',
        'url' => 'Valid URL format',
        'ip' => 'Valid IP address',
        'json' => 'Valid JSON format',
        'uuid' => 'Valid UUID format',
        'date' => 'Valid date format',
        'before' => 'Date must be before specified date',
        'after' => 'Date must be after specified date',
        'confirmed' => 'Field must be confirmed',
        'same' => 'Field must match another field',
        'different' => 'Field must be different from another field',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Constraint Mapping
    |--------------------------------------------------------------------------
    |
    | Map constraint types to database constraint implementations.
    |
    */

    'database_constraints' => [
        'min' => 'CHECK ({column} >= {value})',
        'max' => 'CHECK ({column} <= {value})',
        'between' => 'CHECK ({column} BETWEEN {min} AND {max})',
        'in' => 'CHECK ({column} IN ({values}))',
        'not_in' => 'CHECK ({column} NOT IN ({values}))',
        'regex' => 'CHECK ({column} REGEXP "{pattern}")',
        'length' => 'CHECK (LENGTH({column}) >= {value})',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rule Mapping
    |--------------------------------------------------------------------------
    |
    | Map constraint types to Laravel validation rules.
    |
    */

    'validation_rules' => [
        'min' => 'min:{value}',
        'max' => 'max:{value}',
        'between' => 'between:{min},{max}',
        'in' => 'in:{values}',
        'not_in' => 'not_in:{values}',
        'regex' => 'regex:{pattern}',
        'length' => 'size:{value}',
        'digits' => 'digits:{value}',
        'alpha' => 'alpha',
        'alpha_num' => 'alpha_num',
        'email' => 'email',
        'url' => 'url',
        'ip' => 'ip',
        'json' => 'json',
        'uuid' => 'uuid',
        'date' => 'date',
        'before' => 'before:{date}',
        'after' => 'after:{date}',
        'confirmed' => 'confirmed',
        'same' => 'same:{field}',
        'different' => 'different:{field}',
    ],
]; 
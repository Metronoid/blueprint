<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auditing Generation Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how the auditing extension generates code
    | for your Laravel models with auditing functionality.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Generate Auditing Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate auditing configuration
    | for models that have auditing enabled.
    |
    */
    'generate_auditing' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate Rewind Functionality
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate rewind functionality
    | for models that have rewind enabled.
    |
    */
    'generate_rewind' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate Audits Migration
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate a migration for the
    | audits table if it doesn't already exist.
    |
    */
    'generate_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate Custom Audit Models
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate custom audit models
    | when specified in the configuration.
    |
    */
    'generate_custom_models' => true,

    /*
    |--------------------------------------------------------------------------
    | Auditing Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where auditing related classes will be generated.
    |
    */
    'namespace' => 'App\\Auditing',

    /*
    |--------------------------------------------------------------------------
    | Auditing File Locations
    |--------------------------------------------------------------------------
    |
    | Configure where different types of auditing files are generated.
    |
    */
    'paths' => [
        'models' => 'app/Models',
        'migrations' => 'database/migrations',
        'traits' => 'app/Auditing/Traits',
        'events' => 'app/Auditing/Events',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Auditing Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration values for auditing when not specified
    | in the YAML definition.
    |
    */
    'defaults' => [
        'events' => ['created', 'updated', 'deleted', 'restored'],
        'strict' => false,
        'threshold' => 0,
        'console' => false,
        'empty_values' => false,
        'audit_attach' => false,
        'audit_detach' => false,
        'audit_sync' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Rewind Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration values for rewind functionality when not specified
    | in the YAML definition.
    |
    */
    'rewind_defaults' => [
        'methods' => ['rewindTo', 'rewindToDate', 'rewindSteps', 'getRewindableAudits'],
        'validate' => true,
        'events' => ['rewind'],
        'backup' => true,
        'max_steps' => null,
        'include_attributes' => [],
        'exclude_attributes' => ['id', 'created_at', 'updated_at'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Auditing Configuration
    |--------------------------------------------------------------------------
    |
    | These are the default settings that will be applied to all models
    | that have auditing enabled through Blueprint, unless overridden
    | in the individual model configuration.
    |
    */

    'defaults' => [
        /*
        |--------------------------------------------------------------------------
        | Audit Implementation
        |--------------------------------------------------------------------------
        |
        | Define which Audit model implementation to use.
        |
        */
        'implementation' => OwenIt\Auditing\Models\Audit::class,

        /*
        |--------------------------------------------------------------------------
        | User Keys & Model
        |--------------------------------------------------------------------------
        |
        | Define the user id and user model keys for the Audits.
        |
        */
        'user' => [
            'primary_key' => 'id',
            'foreign_key' => 'user_id',
            'model' => App\Models\User::class,
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Resolvers
        |--------------------------------------------------------------------------
        |
        | Define the resolvers to use for the audit process.
        |
        */
        'resolvers' => [
            'user' => OwenIt\Auditing\Resolvers\UserResolver::class,
            'ip_address' => OwenIt\Auditing\Resolvers\IpAddressResolver::class,
            'user_agent' => OwenIt\Auditing\Resolvers\UserAgentResolver::class,
            'url' => OwenIt\Auditing\Resolvers\UrlResolver::class,
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Events
        |--------------------------------------------------------------------------
        |
        | The Eloquent events that should trigger an audit.
        |
        */
        'events' => [
            'created',
            'updated',
            'deleted',
            'restored',
        ],

        /*
        |--------------------------------------------------------------------------
        | Audit Strict Mode
        |--------------------------------------------------------------------------
        |
        | When enabled, empty values will not be included in the audit.
        |
        */
        'strict' => false,

        /*
        |--------------------------------------------------------------------------
        | Audit Threshold
        |--------------------------------------------------------------------------
        |
        | Maximum number of audits to keep per model. Set to 0 for unlimited.
        |
        */
        'threshold' => 0,

        /*
        |--------------------------------------------------------------------------
        | Audit Empty Values
        |--------------------------------------------------------------------------
        |
        | Whether to include empty values in the audit data.
        |
        */
        'empty_values' => true,

        /*
        |--------------------------------------------------------------------------
        | Audit Console Events
        |--------------------------------------------------------------------------
        |
        | Whether console/CLI events should be audited.
        |
        */
        'console' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Rewind Configuration
    |--------------------------------------------------------------------------
    |
    | These are the default settings for rewind functionality when enabled
    | through Blueprint configuration.
    |
    */
    'rewind' => [
        /*
        |--------------------------------------------------------------------------
        | Rewind Enabled
        |--------------------------------------------------------------------------
        |
        | Whether rewind functionality is enabled by default.
        |
        */
        'enabled' => false,

        /*
        |--------------------------------------------------------------------------
        | Rewind Methods
        |--------------------------------------------------------------------------
        |
        | The rewind methods to generate for models with rewind enabled.
        |
        */
        'methods' => [
            'rewindTo',
            'rewindToDate',
            'rewindSteps',
            'rewindToPrevious',
            'getRewindableAudits',
            'previewRewind',
            'canRewindTo',
            'getRewindDiff',
        ],

        /*
        |--------------------------------------------------------------------------
        | Rewind Validation
        |--------------------------------------------------------------------------
        |
        | Whether to validate rewind operations before executing them.
        |
        */
        'validate' => false,

        /*
        |--------------------------------------------------------------------------
        | Rewind Events
        |--------------------------------------------------------------------------
        |
        | Events to fire when rewind operations are performed.
        |
        */
        'events' => [
            'rewound',
        ],

        /*
        |--------------------------------------------------------------------------
        | Rewind Excluded Attributes
        |--------------------------------------------------------------------------
        |
        | Attributes that should be excluded from rewind operations by default.
        |
        */
        'exclude' => [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ],

        /*
        |--------------------------------------------------------------------------
        | Rewind Confirmation Required
        |--------------------------------------------------------------------------
        |
        | Whether rewind operations require explicit confirmation.
        |
        */
        'require_confirmation' => false,

        /*
        |--------------------------------------------------------------------------
        | Maximum Rewind Steps
        |--------------------------------------------------------------------------
        |
        | Maximum number of steps that can be rewound in a single operation.
        | Set to null for unlimited.
        |
        */
        'max_steps' => null,

        /*
        |--------------------------------------------------------------------------
        | Backup Before Rewind
        |--------------------------------------------------------------------------
        |
        | Whether to create a backup audit entry before performing rewind.
        |
        */
        'backup_before_rewind' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Model-Specific Overrides
    |--------------------------------------------------------------------------
    |
    | You can override the default settings for specific models here.
    | The key should be the model class name.
    |
    */
    'models' => [
        // Example:
        // App\Models\User::class => [
        //     'events' => ['created', 'updated'],
        //     'exclude' => ['password', 'remember_token'],
        //     'rewind' => [
        //         'enabled' => true,
        //         'max_steps' => 10,
        //         'exclude' => ['password', 'remember_token', 'email_verified_at'],
        //     ],
        // ],
    ],
]; 
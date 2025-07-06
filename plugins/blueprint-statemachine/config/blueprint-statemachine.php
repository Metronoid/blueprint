<?php

return [
    /*
    |--------------------------------------------------------------------------
    | State Machine Generation Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how the state machine extension generates code
    | for your Laravel models with state machine functionality.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Trait
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate a trait for each model with
    | state machine functionality. This trait contains all the state machine
    | methods and can be included in your model.
    |
    */
    'generate_trait' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Methods
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate state machine methods directly
    | in the model class instead of using a trait.
    |
    */
    'generate_methods_in_model' => false,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Events
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate Laravel events for state
    | transitions. These events can be used to trigger other actions.
    |
    */
    'generate_events' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Observers
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate model observers to handle
    | state machine events and callbacks.
    |
    */
    'generate_observers' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Middleware
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate middleware for protecting
    | routes based on model state.
    |
    */
    'generate_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Scopes
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate query scopes for filtering
    | models by their state.
    |
    */
    'generate_scopes' => true,

    /*
    |--------------------------------------------------------------------------
    | Generate State Machine Tests
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate basic test files for the
    | state machine functionality.
    |
    */
    'generate_tests' => true,

    /*
    |--------------------------------------------------------------------------
    | State History Tracking
    |--------------------------------------------------------------------------
    |
    | When enabled, the extension will generate a state history table and
    | model to track all state transitions.
    |
    */
    'track_state_history' => true,

    /*
    |--------------------------------------------------------------------------
    | State Machine Namespace
    |--------------------------------------------------------------------------
    |
    | The namespace where state machine related classes will be generated.
    |
    */
    'namespace' => 'App\\StateMachine',

    /*
    |--------------------------------------------------------------------------
    | State Machine File Locations
    |--------------------------------------------------------------------------
    |
    | Configure where different types of state machine files are generated.
    |
    */
    'paths' => [
        'traits' => 'app/StateMachine/Traits',
        'events' => 'app/StateMachine/Events',
        'observers' => 'app/StateMachine/Observers',
        'middleware' => 'app/StateMachine/Middleware',
        'tests' => 'tests/Feature/StateMachine',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default State Machine Configuration
    |--------------------------------------------------------------------------
    |
    | Default configuration values for state machines when not specified
    | in the YAML definition.
    |
    */
    'defaults' => [
        'track_history' => true,
        'validate_transitions' => true,
        'fire_events' => true,
    ],
]; 
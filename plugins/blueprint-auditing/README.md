# Blueprint Auditing Extension

A powerful Blueprint extension that adds Laravel Auditing package integration to your Laravel models. Define auditing configurations and rewind functionality directly in your Blueprint YAML files.

## Features

- **Auditing Configuration**: Automatically configure Laravel Auditing for models
- **Rewind Functionality**: Add time-travel capabilities to your models
- **Unrewindable Audits**: Mark specific audits as unrewindable for compliance and security
- **Git-like Versioning**: Full Git-like branching, committing, and merging for models
- **Origin Tracking**: Comprehensive tracking of what caused each change
- **Request Context**: Track HTTP requests, routes, and controller actions
- **Side Effects Tracking**: Monitor cascading changes across related models
- **Causality Chain**: Track the chain of events that led to changes
- **Migration Generation**: Automatic generation of audits table migration
- **Custom Audit Models**: Generate custom audit model implementations
- **Event Tracking**: Track model changes with comprehensive audit trails
- **User Attribution**: Track which user made changes
- **Flexible Configuration**: Support for all Laravel Auditing configuration options

## Installation

### Option 1: Plugin Directory (Recommended)

1. Clone or copy this plugin to your `plugins/` directory:

```bash
mkdir -p plugins
cd plugins
git clone https://github.com/blueprint-extensions/auditing.git blueprint-auditing
```

2. Blueprint will automatically discover and register the plugin.

### Option 2: Composer Package

1. Add the plugin to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./plugins/blueprint-auditing"
        }
    ],
    "require": {
        "blueprint-extensions/auditing": "*"
    }
}
```

2. Install the package:

```bash
composer install
```

3. The plugin will be automatically discovered and registered.

### Option 3: Manual Registration

If you need to manually register the plugin, add it to your `config/app.php`:

```php
'providers' => [
    // ... other providers
    BlueprintExtensions\Auditing\BlueprintAuditingServiceProvider::class,
],
```

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=blueprint-auditing-config
```

This will create `config/blueprint-auditing.php` where you can customize the plugin behavior.

## Usage

### Basic Auditing

Enable auditing for a model with simple configuration:

```yaml
models:
  User:
    name: string:255
    email: string:191 unique
    auditing: true  # Simple boolean enable
```

### Detailed Auditing Configuration

Configure auditing with specific options:

```yaml
models:
  Post:
    title: string:255
    content: text
    author_id: id foreign:users
    auditing:
      events: [created, updated, deleted]
      exclude: [id, created_at, updated_at]
      strict: true
      threshold: 0
      console: false
      empty_values: false
      user: 'auth()->user()'
      tags: [blog, content]
```

### Auditing with Origin Tracking

Track the origin and context of every change:

```yaml
models:
  User:
    name: string:255
    email: string:191 unique
    password: string
    auditing:
      events: [created, updated, deleted]
      exclude: [password, remember_token]
      origin_tracking:
        enabled: true
        track_request: true
        track_session: true
        track_route: true
        track_controller_action: true
        track_request_data: true
        track_side_effects: true
        track_causality_chain: true
        group_audits: true
        exclude_request_fields: [password, _token, _method]
        include_request_fields: [name, email, bio]
        track_origin_types: [request, console, job, observer]
```

### Auditing with Rewind Functionality

Add time-travel capabilities to your models:

```yaml
models:
  Order:
    customer_id: id foreign:users
    total: decimal:8,2
    status: enum:pending,processing,shipped,delivered
    auditing:
      events: [created, updated, deleted]
      rewind:
        enabled: true
        methods: [rewindTo, rewindToDate, rewindSteps, getRewindableAudits]
        validate: true
        events: [rewind]
        backup: true
        max_steps: 10
        include_attributes: [total, status]
        exclude_attributes: [id, created_at, updated_at]
```

### Auditing with Unrewindable Flag

Mark specific audits as unrewindable for compliance and security:

```yaml
models:
  Transaction:
    transaction_id: string:50 unique
    amount: decimal:15,2
    reconciled: boolean default:false
    auditing:
      events: [created, updated]
      rewind:
        enabled: true
        validate: true
        max_steps: 3
        backup: true
        # Mark certain events as unrewindable
        unrewindable_events: [reconciled, compliance_approved]
```

### Auditing with Git-like Versioning

Add Git-like branching, committing, and merging to your models:

```yaml
models:
  Document:
    title: string:255
    content: longtext
    status: enum:draft,review,approved,published
    auditing:
      events: [created, updated, deleted]
      git_versioning:
        enabled: true
        auto_initialize: true
        default_branch: main
        auto_commit: false
        commit_on_save: true
        merge_strategies: [fast-forward, merge, rebase]
        tag_creation: semantic
        branch_naming: kebab-case
        commit_message_template: '{action} document: {title}'
        include_attributes: [title, content, status]
        exclude_attributes: [id, created_at, updated_at]
```

### Custom Audit Model

Specify a custom audit model implementation:

```yaml
models:
  Product:
    name: string:255
    price: decimal:8,2
    auditing:
      events: [created, updated, deleted]
      implementation: 'App\\Models\\CustomAudit'
```

## Configuration Options

### Auditing Configuration

- `events`: Array of events to audit (created, updated, deleted, restored)
- `exclude`: Array of attributes to exclude from auditing
- `include`: Array of attributes to include in auditing (if specified, only these are audited)
- `strict`: Whether to use strict mode
- `threshold`: Minimum number of changes to trigger an audit
- `console`: Whether to audit console commands
- `empty_values`: Whether to audit empty values
- `user`: User resolver method
- `implementation`: Custom audit model class
- `resolvers`: Array of custom resolvers
- `tags`: Array of tags for the audit
- `transformations`: Array of attribute transformations
- `audit_attach`: Whether to audit attach operations
- `audit_detach`: Whether to audit detach operations
- `audit_sync`: Whether to audit sync operations

### Origin Tracking Configuration

- `enabled`: Whether origin tracking is enabled
- `track_request`: Whether to track request ID
- `track_session`: Whether to track session ID
- `track_route`: Whether to track route name
- `track_controller_action`: Whether to track controller action
- `track_request_data`: Whether to track request data (sanitized)
- `track_response_data`: Whether to track response data
- `track_side_effects`: Whether to track side effects in other models
- `track_causality_chain`: Whether to track the chain of events
- `group_audits`: Whether to group related audits together
- `exclude_request_fields`: Array of request fields to exclude
- `include_request_fields`: Array of request fields to include (overrides exclude)
- `track_origin_types`: Array of origin types to track
- `resolvers`: Array of custom resolvers for origin data

### Rewind Configuration

- `enabled`: Whether rewind functionality is enabled
- `methods`: Array of rewind methods to generate
- `validate`: Whether to validate rewind operations
- `events`: Array of events to fire on rewind
- `backup`: Whether to backup current state before rewind
- `max_steps`: Maximum number of steps to rewind
- `include_attributes`: Array of attributes to include in rewind
- `exclude_attributes`: Array of attributes to exclude from rewind

### Git-like Versioning Configuration

- `enabled`: Whether Git-like versioning is enabled
- `auto_initialize`: Whether to automatically initialize Git versioning
- `default_branch`: The default branch name (usually 'main')
- `auto_commit`: Whether to automatically commit changes
- `commit_on_save`: Whether to commit changes when model is saved
- `allow_force_delete`: Whether to allow force deletion of branches
- `merge_strategies`: Array of allowed merge strategies
- `default_merge_strategy`: Default merge strategy to use
- `tag_creation`: Tag creation mode ('manual', 'auto', 'semantic')
- `branch_naming`: Branch naming convention
- `commit_message_template`: Template for commit messages
- `include_attributes`: Array of attributes to include in versioning
- `exclude_attributes`: Array of attributes to exclude from versioning
- `max_branches_per_model`: Maximum number of branches per model
- `max_commits_per_branch`: Maximum number of commits per branch
- `auto_cleanup_old_branches`: Whether to automatically cleanup old branches
- `cleanup_days_threshold`: Number of days before cleanup

## Generated Components

When you run `php artisan blueprint:build`, the extension generates:

### 1. Model Auditing Configuration

The extension automatically adds auditing configuration to your models:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use BlueprintExtensions\Auditing\Traits\RewindableTrait;

class Post extends Model implements Auditable
{
    use AuditableTrait, RewindableTrait;

    // Auditing Configuration
    protected $auditEvents = ['created', 'updated', 'deleted'];
    protected $auditExclude = ['id', 'created_at', 'updated_at'];
    protected $auditStrict = true;
    protected $auditThreshold = 0;
    protected $auditConsole = false;
    protected $auditEmptyValues = false;
    protected $auditTags = ['blog', 'content'];

    // Origin Tracking Configuration
    protected $originTrackingEnabled = true;
    protected $trackRequest = true;
    protected $trackSession = true;
    protected $trackRoute = true;
    protected $trackControllerAction = true;
    protected $trackRequestData = true;
    protected $trackSideEffects = true;
    protected $trackCausalityChain = true;
    protected $groupAudits = true;
    protected $excludeRequestFields = ['password', '_token', '_method'];
    protected $includeRequestFields = ['name', 'email', 'bio'];
    protected $trackOriginTypes = ['request', 'console', 'job', 'observer'];

    // Rewind Configuration
    protected $rewindMethods = ['rewindTo', 'rewindToDate', 'rewindSteps', 'getRewindableAudits'];
    protected $rewindValidate = true;
    protected $rewindEvents = ['rewind'];
    protected $rewindBackup = true;
    protected $rewindMaxSteps = 10;
    protected $rewindIncludeAttributes = ['total', 'status'];
    protected $rewindExcludeAttributes = ['id', 'created_at', 'updated_at'];
}
```

### 2. Audits Migration

If the audits table doesn't exist, the extension generates a migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event');
            $table->morphs('auditable');
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->text('url')->nullable();
            $table->ipAddress('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('tags')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
```

### 3. Custom Audit Model (if specified)

If you specify a custom audit model, the extension generates it:

```php
<?php

namespace App\Models;

use OwenIt\Auditing\Models\Audit as BaseAudit;

class CustomAudit extends BaseAudit
{
    // Custom audit model implementation
    // Add your custom methods and properties here
}
```

## Using Origin Tracking

The origin tracking functionality provides comprehensive information about what caused each change:

```php
// Get audits with origin information
$audits = $user->audits()->with('user')->get();

foreach ($audits as $audit) {
    echo "Change made by: " . $audit->user->name;
    echo "Route: " . $audit->getMetadata('route_name');
    echo "Controller: " . $audit->getMetadata('controller_action');
    echo "Request ID: " . $audit->getMetadata('request_id');
    echo "Origin Type: " . $audit->getMetadata('origin_type');
    echo "Request Data: " . json_encode($audit->getMetadata('request_data'));
}

// Get side effects for a specific request
$sideEffects = $user->getSideEffects();

// Track side effects manually
$user->trackSideEffects([
    'related_model' => 'Post',
    'action' => 'created',
    'reason' => 'User registration'
]);

// Get audits grouped by request
$groupedAudits = $user->audits()
    ->whereNotNull('audit_group_id')
    ->orderBy('created_at', 'desc')
    ->get()
    ->groupBy('audit_group_id');
```

## Using Rewind Functionality

The rewind functionality allows you to travel back in time with your models:

```php
// Rewind to a specific audit
$post->rewindTo($auditId);

// Rewind to a specific date
$post->rewindToDate('2023-01-15 10:30:00');

// Rewind a specific number of steps
$post->rewindSteps(3);

// Get all rewindable audits
$audits = $post->getRewindableAudits();

// Check if rewind is possible
if ($post->canRewindTo($auditId)) {
    $post->rewindTo($auditId);
}

// Get the difference between current and target state
$diff = $post->getRewindDiff($auditId);
```

## Using Unrewindable Audit Functionality

The unrewindable functionality allows you to mark specific audits as unrewindable for compliance and security:

```php
// Mark a single audit as unrewindable
$transaction->markAuditAsUnrewindable($auditId, 'Compliance requirement', ['regulation' => 'SOX']);

// Mark multiple audits as unrewindable
$results = $transaction->markAuditsAsUnrewindable([1, 2, 3], 'Bulk compliance update');

// Mark audits in a date range as unrewindable
$results = $transaction->markAuditsInRangeAsUnrewindable(
    '2023-01-01', 
    '2023-01-31', 
    'End of month reconciliation'
);

// Mark audits by event type as unrewindable
$results = $transaction->markAuditsByEventAsUnrewindable('reconciled', 'Financial compliance');

// Mark audits by user as unrewindable
$results = $transaction->markAuditsByUserAsUnrewindable($userId, 'User termination');

// Check if an audit can be rewound
if ($transaction->canRewindAudit($auditId)) {
    // Safe to rewind
}

// Get rewindable vs unrewindable statistics
$stats = $transaction->getRewindableStatistics();

// Get unrewindable audits
$unrewindableAudits = $transaction->getUnrewindableAudits();

// Get the reason why an audit is unrewindable
$reason = $transaction->getUnrewindableReason($auditId);
```

### API Endpoints

The plugin provides API endpoints for managing unrewindable audits:

```php
// Mark a single audit as unrewindable
POST /api/auditing/audits/{audit}/mark-unrewindable
{
    "reason": "Compliance requirement",
    "metadata": {"regulation": "SOX"}
}

// Mark multiple audits as unrewindable
POST /api/auditing/audits/mark-unrewindable-bulk
{
    "audit_ids": [1, 2, 3],
    "reason": "Bulk compliance update"
}

// Get rewindable audits for a model
GET /api/auditing/models/{modelType}/{modelId}/rewindable-audits?limit=50

// Get unrewindable audits for a model
GET /api/auditing/models/{modelType}/{modelId}/unrewindable-audits?limit=50

// Get rewindable statistics
GET /api/auditing/models/{modelType}/{modelId}/rewindable-statistics

// Mark audits by criteria as unrewindable
POST /api/auditing/models/{modelType}/{modelId}/mark-audits-unrewindable
{
    "criteria": "date_range",
    "start_date": "2023-01-01",
    "end_date": "2023-01-31",
    "reason": "End of month reconciliation"
}
```

## Using Git-like Versioning

The Git-like versioning functionality provides full Git-like capabilities for your models:

### Basic Git Operations

```php
// Initialize Git versioning
$document->initializeGitVersioning();

// Create a new branch
$branchId = $document->createBranch('feature/new-content', null, 'Add new content section');

// Switch to a branch
$document->checkoutBranch($branchId);

// Stage changes
$document->stageChanges(['title' => 'Updated Title', 'content' => 'New content']);

// Commit changes
$commitId = $document->commit('Update document title and content');

// List branches
$branches = $document->listBranches();

// Get commit history
$commits = $document->getCommitHistory();
```

### Advanced Git Operations

```php
// Merge branches
$mergeCommitId = $document->mergeBranch($sourceBranchId, 'merge');

// Create tags
$tagId = $document->createTag('v1.0.0', 'Release version 1.0.0');

// Reset to a specific commit
$document->resetToCommit($commitId, 'mixed');

// Get diff between commits
$diff = $document->getCommitDiff($commitId1, $commitId2);

// Get current branch info
$branchInfo = $document->getCurrentBranchInfo();

// Delete a branch
$document->deleteBranch($branchId, false);
```

### Branch Management

```php
// Create feature branch
$featureBranchId = $document->createBranch('feature/user-authentication');

// Work on feature branch
$document->checkoutBranch($featureBranchId);
$document->stageChanges(['content' => 'Add authentication section']);
$document->commit('Add user authentication documentation');

// Switch back to main
$document->checkoutBranch($mainBranchId);

// Merge feature branch
$document->mergeBranch($featureBranchId, 'merge');

// Delete feature branch after merge
$document->deleteBranch($featureBranchId);
```

### Conflict Resolution

```php
try {
    $document->mergeBranch($sourceBranchId, 'merge');
} catch (MergeConflictException $e) {
    $conflicts = $e->getConflicts();
    
    // Resolve conflicts manually
    foreach ($conflicts as $attribute => $conflict) {
        // Choose which value to keep
        $document->$attribute = $conflict['ours']; // or $conflict['theirs']
    }
    
    // Complete the merge
    $document->commit('Resolve merge conflicts');
}
```

## Configuration

The extension can be configured via the `config/blueprint-auditing.php` file:

```php
<?php

return [
    // Enable/disable different generation types
    'generate_auditing' => true,
    'generate_rewind' => true,
    'generate_migrations' => true,
    'generate_custom_models' => true,

    // Namespace for generated classes
    'namespace' => 'App\\Auditing',

    // File paths
    'paths' => [
        'models' => 'app/Models',
        'migrations' => 'database/migrations',
        'traits' => 'app/Auditing/Traits',
        'events' => 'app/Auditing/Events',
    ],

    // Default configuration values
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

    // Default rewind configuration
    'rewind_defaults' => [
        'methods' => ['rewindTo', 'rewindToDate', 'rewindSteps', 'getRewindableAudits'],
        'validate' => true,
        'events' => ['rewind'],
        'backup' => true,
        'max_steps' => null,
        'include_attributes' => [],
        'exclude_attributes' => ['id', 'created_at', 'updated_at'],
    ],
];
```

## Examples

Check out the `examples/` directory for comprehensive examples:

- `simple-auditing.yaml` - Basic auditing examples for beginners
- `blog-with-auditing.yaml` - Blog system with comprehensive auditing
- `rewind-example.yaml` - E-commerce system with rewind functionality
- `git-versioning-example.yaml` - Document management with Git-like versioning
- `advanced-auditing.yaml` - Advanced auditing scenarios (document management, workflows, etc.)

## Dependencies

This extension requires:

- **Laravel Auditing**: `owen-it/laravel-auditing` ^13.0
- **Blueprint**: `laravel-shift/blueprint` ^2.0

## Best Practices

1. **Use appropriate events**: Only audit events that are meaningful for your application
2. **Exclude sensitive data**: Always exclude sensitive fields from auditing
3. **Set reasonable thresholds**: Use thresholds to avoid excessive audit records
4. **Test rewind functionality**: Thoroughly test rewind operations before production
5. **Monitor audit table size**: Audits can grow large over time
6. **Use tags**: Tag audits for better organization and filtering

## Troubleshooting

### Common Issues

1. **Missing audits table**: Run migrations to create the audits table
2. **Rewind not working**: Ensure the RewindableTrait is properly included
3. **Performance issues**: Consider indexing the audits table appropriately
4. **Memory issues**: Use pagination when working with large audit histories

### Debug Mode

Enable debug mode to see detailed generation:

```bash
php artisan blueprint:build --debug
```

## Plugin Architecture

This plugin uses Blueprint's new plugin system introduced in v2.0. The plugin consists of:

- **Plugin Class**: `BlueprintAuditingPlugin` - Main plugin entry point
- **Lexer**: `AuditingLexer` - Parses auditing syntax from YAML
- **Generator**: `AuditingGenerator` - Generates auditing-related files
- **Service Provider**: `BlueprintAuditingServiceProvider` - Laravel service provider
- **Trait**: `RewindableTrait` - Provides rewind functionality

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This extension is open-sourced software licensed under the [MIT license](LICENSE). 
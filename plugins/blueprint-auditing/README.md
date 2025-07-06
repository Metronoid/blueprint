# Blueprint Auditing Extension

A powerful Blueprint extension that adds Laravel Auditing package integration to your Laravel models. Define auditing configurations and rewind functionality directly in your Blueprint YAML files.

## Features

- **Auditing Configuration**: Automatically configure Laravel Auditing for models
- **Rewind Functionality**: Add time-travel capabilities to your models
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

### Rewind Configuration

- `enabled`: Whether rewind functionality is enabled
- `methods`: Array of rewind methods to generate
- `validate`: Whether to validate rewind operations
- `events`: Array of events to fire on rewind
- `backup`: Whether to backup current state before rewind
- `max_steps`: Maximum number of steps to rewind
- `include_attributes`: Array of attributes to include in rewind
- `exclude_attributes`: Array of attributes to exclude from rewind

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
# Blueprint Constraints Extension

A powerful extension for Laravel Blueprint that adds support for column constraints and validation rules directly in your Blueprint YAML files.

## Features

- **Database Constraints**: Automatically generate database check constraints
- **Validation Rules**: Generate Laravel validation rules for Form Requests
- **Model Mutators**: Optional model mutators for runtime validation
- **Inline Syntax**: Define constraints directly in column definitions
- **Model-Level Constraints**: Define constraints at the model level for better organization
- **Comprehensive Support**: Wide range of constraint types supported

## Installation

### Option 1: Plugin Directory (Recommended)

1. Clone or copy this plugin to your `plugins/` directory:

```bash
mkdir -p plugins
cd plugins
git clone https://github.com/blueprint-extensions/constraints.git blueprint-constraints
```

2. Blueprint will automatically discover and register the plugin.

### Option 2: Composer Package

1. Add the plugin to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./plugins/blueprint-constraints"
        }
    ],
    "require": {
        "blueprint-extensions/constraints": "*"
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
    BlueprintExtensions\Constraints\BlueprintConstraintsServiceProvider::class,
],
```

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=blueprint-constraints-config
```

This will create `config/blueprint-constraints.php` where you can customize the plugin behavior.

## Usage

### Inline Constraint Syntax

Define constraints directly in your column definitions:

```yaml
models:
  Product:
    name: string:255
    price: decimal:8,2 min:0.01 max:10000
    quantity: integer min:0 max:1000
    rating: integer between:1,5
    status: enum:active,inactive,discontinued in:active,inactive,discontinued
    sku: string:50 regex:^[A-Z]{3}-\d{4}$
    email: string:191 unique email
    website: string:255 url
    age: integer min:13 max:120
```

### Model-Level Constraints

For better organization, define constraints at the model level:

```yaml
models:
  Employee:
    name: string:255
    email: string:191 unique
    salary: decimal:10,2
    department: string:100
    hire_date: date
    performance_rating: integer
    constraints:
      salary: 
        - min:30000
        - max:500000
      performance_rating:
        - between:1,10
      department:
        - in:Engineering,Marketing,Sales,HR,Finance
      hire_date:
        - after:2020-01-01
        - before:today
```

## Supported Constraint Types

### Numeric Constraints
- `min:value` - Minimum value
- `max:value` - Maximum value  
- `between:min,max` - Value must be between two values
- `digits:value` - Exact number of digits

### String Constraints
- `length:value` - Exact string length
- `alpha` - Alphabetic characters only
- `alpha_num` - Alphanumeric characters only
- `regex:pattern` - Must match regular expression

### List Constraints
- `in:value1,value2,value3` - Value must be in list
- `not_in:value1,value2,value3` - Value must not be in list

### Format Constraints
- `email` - Valid email format
- `url` - Valid URL format
- `ip` - Valid IP address
- `json` - Valid JSON format
- `uuid` - Valid UUID format

### Date Constraints
- `date` - Valid date format
- `before:date` - Date must be before specified date
- `after:date` - Date must be after specified date

### Field Comparison
- `same:field` - Must match another field
- `different:field` - Must be different from another field
- `confirmed` - Must be confirmed (for password fields)

## Generated Components

When you run `php artisan blueprint:build`, the extension generates:

### 1. Database Constraints Migration

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_price_min CHECK (price >= 0.01)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_price_max CHECK (price <= 10000)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_quantity_min CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_rating_between CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_price_min');
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_price_max');
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_quantity_min');
        DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_rating_between');
    }
};
```

### 2. Validation Rules

The extension automatically adds validation rules to existing Form Request classes or creates new validation rule classes:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0.01|max:10000',
            'quantity' => 'required|integer|min:0|max:1000',
            'rating' => 'required|integer|between:1,5',
            'status' => 'required|in:active,inactive,discontinued',
            'sku' => 'required|string|max:50|regex:/^[A-Z]{3}-\d{4}$/',
            'email' => 'required|email',
            'website' => 'nullable|url',
            'age' => 'required|integer|min:13|max:120',
        ];
    }
}
```

### 3. Model Mutators (Optional)

Enable model mutators in the configuration to add runtime validation:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /**
     * Set the price attribute with constraint validation.
     */
    public function setPriceAttribute($value): void
    {
        if ($value < 0.01) {
            throw new \InvalidArgumentException('Value must be at least 0.01');
        }
        if ($value > 10000) {
            throw new \InvalidArgumentException('Value must be at most 10000');
        }
        
        $this->attributes['price'] = $value;
    }

    /**
     * Set the quantity attribute with constraint validation.
     */
    public function setQuantityAttribute($value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Value must be at least 0');
        }
        if ($value > 1000) {
            throw new \InvalidArgumentException('Value must be at most 1000');
        }
        
        $this->attributes['quantity'] = $value;
    }
}
```

## Configuration

The extension can be configured via the `config/blueprint-constraints.php` file:

```php
<?php

return [
    // Enable/disable different generation types
    'generate_database_constraints' => true,
    'generate_validation_rules' => true,
    'generate_model_mutators' => false,

    // Supported constraint types
    'supported_constraints' => [
        'min' => 'Minimum value constraint',
        'max' => 'Maximum value constraint',
        'between' => 'Value must be between two values',
        // ... more constraints
    ],

    // Database constraint SQL templates
    'database_constraints' => [
        'min' => 'CHECK ({column} >= {value})',
        'max' => 'CHECK ({column} <= {value})',
        'between' => 'CHECK ({column} BETWEEN {min} AND {max})',
        // ... more mappings
    ],

    // Validation rule templates
    'validation_rules' => [
        'min' => 'min:{value}',
        'max' => 'max:{value}',
        'between' => 'between:{min},{max}',
        // ... more mappings
    ],
];
```

## Examples

Check out the `examples/` directory for comprehensive examples:

- `basic-constraints.yaml` - Inline constraint syntax examples
- `model-level-constraints.yaml` - Model-level constraint definitions

## Database Support

The extension generates database constraints that work with:

- **PostgreSQL** - Full support for all constraint types
- **MySQL** - Limited support (check constraints available in MySQL 8.0.16+)
- **SQLite** - Limited support for check constraints
- **SQL Server** - Full support for check constraints

## Best Practices

1. **Use appropriate constraints**: Choose the right constraint type for your data
2. **Combine with Laravel validation**: Database constraints are a last line of defense
3. **Test thoroughly**: Ensure constraints work with your application logic
4. **Document constraints**: Use clear constraint names and comments
5. **Consider performance**: Database constraints can impact INSERT/UPDATE performance

## Troubleshooting

### Common Issues

1. **Constraint conflicts**: Ensure constraint values don't conflict with existing data
2. **Migration failures**: Check database compatibility for constraint types
3. **Validation rule conflicts**: Ensure generated rules don't conflict with existing validation

### Debug Mode

Enable debug mode to see detailed constraint generation:

```bash
php artisan blueprint:build --debug
```

## Plugin Architecture

This plugin uses Blueprint's new plugin system introduced in v2.0. The plugin consists of:

- **Plugin Class**: `BlueprintConstraintsPlugin` - Main plugin entry point
- **Lexer**: `ConstraintsLexer` - Parses constraint syntax from YAML
- **Generator**: `ConstraintsGenerator` - Generates constraint-related files
- **Service Provider**: `BlueprintConstraintsServiceProvider` - Laravel service provider

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This extension is open-sourced software licensed under the [MIT license](LICENSE). 
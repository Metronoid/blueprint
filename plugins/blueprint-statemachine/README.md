# Blueprint State Machine Extension

A powerful Blueprint extension that adds state machine functionality to Laravel models. Define complex workflows and state transitions directly in your Blueprint YAML files.

## Features

- **State Machine Definition**: Define states, transitions, guards, and callbacks in YAML
- **Automatic Code Generation**: Generate traits, events, observers, middleware, and tests
- **State History Tracking**: Optional tracking of all state transitions
- **Guard Methods**: Protect transitions with custom validation logic
- **Callbacks**: Execute code before and after state transitions
- **Events**: Fire Laravel events during state transitions
- **Query Scopes**: Automatic generation of query scopes for each state
- **Middleware**: Route protection based on model state
- **Comprehensive Testing**: Generate complete test suites for state machines

## Installation

### Option 1: Plugin Directory (Recommended)

1. Clone or copy this plugin to your `plugins/` directory:

```bash
mkdir -p plugins
cd plugins
git clone https://github.com/blueprint-extensions/statemachine.git blueprint-statemachine
```

2. Blueprint will automatically discover and register the plugin.

### Option 2: Composer Package

1. Add the plugin to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./plugins/blueprint-statemachine"
        }
    ],
    "require": {
        "blueprint-extensions/statemachine": "*"
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
    BlueprintExtensions\StateMachine\BlueprintStateMachineServiceProvider::class,
],
```

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=blueprint-statemachine-config
```

This will create `config/blueprint-statemachine.php` where you can customize the plugin behavior.

## Basic Usage

Define a state machine in your Blueprint YAML file:

```yaml
models:
  Order:
    customer_id: id foreign:users
    total: decimal:8,2
    status: enum:pending,processing,shipped,delivered,cancelled
    
    state_machine:
      field: status
      initial: pending
      
      transitions:
        process: [pending, processing]
        ship: [processing, shipped]
        deliver: [shipped, delivered]
        cancel: [pending, processing, cancelled]
        
      guards:
        ship: hasValidAddress
        deliver: isShipped
        cancel: canBeCancelled
        
      callbacks:
        before_process: validatePayment
        after_process: sendProcessingNotification
        after_ship: sendShippingNotification
        after_deliver: sendDeliveryNotification
```

## Configuration Options

### State Machine Definition

```yaml
state_machine:
  field: status                    # Field that holds the state (default: 'status')
  initial: pending                 # Initial state when model is created
  track_history: true             # Track state transitions (default: true)
  validate_transitions: true      # Validate transitions (default: true)
  fire_events: true              # Fire events during transitions (default: true)
```

### Transitions

Define transitions using array format:

```yaml
transitions:
  process: [pending, processing]           # From 'pending' to 'processing'
  ship: [processing, shipped]              # From 'processing' to 'shipped'
  cancel: [pending, processing, cancelled] # From 'pending' OR 'processing' to 'cancelled'
```

Or using string format:

```yaml
transitions:
  process: "pending -> processing"
  ship: "processing -> shipped"
  cancel: "pending, processing -> cancelled"
```

### Guards

Protect transitions with guard methods:

```yaml
guards:
  ship: hasValidAddress        # Simple guard method
  deliver: isShipped          # Another guard method
  cancel: canBeCancelled      # Guard for cancellation
```

### Callbacks

Execute code before and after transitions:

```yaml
callbacks:
  before_process: validatePayment           # Before processing
  after_process: sendProcessingNotification # After processing
  after_ship: sendShippingNotification     # After shipping
  after_deliver: sendDeliveryNotification  # After delivery
```

### States

Define explicit state configurations:

```yaml
states:
  pending:
    label: "Pending"
    color: "yellow"
    description: "Order is awaiting processing"
  processing:
    label: "Processing"
    color: "blue"
    description: "Order is being processed"
  shipped:
    label: "Shipped"
    color: "purple"
    description: "Order has been shipped"
  delivered:
    label: "Delivered"
    color: "green"
    description: "Order has been delivered"
  cancelled:
    label: "Cancelled"
    color: "red"
    description: "Order has been cancelled"
```

## Generated Code

The extension generates several types of files:

### 1. State Machine Trait

A trait containing all state machine functionality:

```php
// app/StateMachine/Traits/OrderStateMachine.php
trait OrderStateMachine
{
    // State constants
    public const STATE_PENDING = 'pending';
    public const STATE_PROCESSING = 'processing';
    // ... more constants

    // Transition methods
    public function process(): bool { /* ... */ }
    public function ship(): bool { /* ... */ }
    public function deliver(): bool { /* ... */ }
    public function cancel(): bool { /* ... */ }

    // Utility methods
    public function getAvailableTransitions(): array { /* ... */ }
    public function canTransition(string $transition): bool { /* ... */ }
    public function isPending(): bool { /* ... */ }
    public function isProcessing(): bool { /* ... */ }
    // ... more utility methods

    // Query scopes
    public function scopePending($query) { /* ... */ }
    public function scopeProcessing($query) { /* ... */ }
    // ... more scopes
}
```

### 2. Events

Events fired during state transitions:

```php
// app/StateMachine/Events/OrderProcess.php
class OrderProcess
{
    public Order $model;
    public string $phase; // 'before' or 'after'
    
    public function __construct(Order $model, string $phase = 'after')
    {
        $this->model = $model;
        $this->phase = $phase;
    }
}
```

### 3. Observer

Model observer to handle initial state setting:

```php
// app/StateMachine/Observers/OrderStateMachineObserver.php
class OrderStateMachineObserver
{
    public function creating(Order $model): void
    {
        if (empty($model->status)) {
            $model->status = 'pending';
        }
    }
}
```

### 4. Middleware

Route protection based on model state:

```php
// app/StateMachine/Middleware/EnsureOrderState.php
class EnsureOrderState
{
    public function handle(Request $request, Closure $next, string $state = null, string $parameter = 'id'): mixed
    {
        if ($state) {
            $model = Order::findOrFail($request->route($parameter));
            
            if ($model->status !== $state) {
                abort(403, 'Access denied. Resource is not in the required state.');
            }
        }

        return $next($request);
    }
}
```

### 5. State History

Migration and model for tracking state transitions:

```php
// Migration: create_orders_state_history_table.php
Schema::create('orders_state_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
    $table->string('from_state');
    $table->string('to_state');
    $table->string('transition');
    $table->json('metadata')->nullable();
    $table->timestamp('created_at');
    
    $table->index(['order_id', 'created_at']);
});

// Model: OrderStateHistory.php
class OrderStateHistory extends Model
{
    protected $fillable = [
        'from_state', 'to_state', 'transition', 'metadata', 'created_at'
    ];
    
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

### 6. Tests

Comprehensive test suite for state machine functionality:

```php
// tests/Feature/StateMachine/OrderStateMachineTest.php
class OrderStateMachineTest extends TestCase
{
    public function test_process_transition(): void
    {
        $model = Order::factory()->create(['status' => 'pending']);
        
        $result = $model->process();
        
        $this->assertTrue($result);
        $this->assertEquals('processing', $model->status);
    }
    
    // ... more tests
}
```

## Usage in Controllers

You can use the generated state machine methods in your controllers:

```php
class OrderController extends Controller
{
    public function process(Order $order)
    {
        if ($order->process()) {
            return redirect()->route('orders.show', $order)
                ->with('success', 'Order processed successfully');
        }
        
        return back()->with('error', 'Unable to process order');
    }
    
    public function ship(Order $order)
    {
        if ($order->ship()) {
            return redirect()->route('orders.show', $order)
                ->with('success', 'Order shipped successfully');
        }
        
        return back()->with('error', 'Unable to ship order');
    }
}
```

## Using Middleware

Protect routes based on model state:

```php
// routes/web.php
Route::middleware(['ensure.order.state:processing'])->group(function () {
    Route::post('/orders/{order}/ship', [OrderController::class, 'ship']);
});

Route::middleware(['ensure.order.state:shipped'])->group(function () {
    Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver']);
});
```

## Query Scopes

Use generated query scopes to filter models by state:

```php
// Get all pending orders
$pendingOrders = Order::pending()->get();

// Get all processing orders
$processingOrders = Order::processing()->get();

// Get all shipped orders
$shippedOrders = Order::shipped()->get();
```

## State History

Track and query state transitions:

```php
// Get state history for an order
$history = $order->stateHistory()->orderBy('created_at', 'desc')->get();

// Get the last transition
$lastTransition = $order->stateHistory()->latest('created_at')->first();
```

## Configuration

The extension can be configured via the `config/blueprint-statemachine.php` file:

```php
return [
    'generate_trait' => true,           // Generate state machine traits
    'generate_events' => true,          // Generate events
    'generate_observers' => true,       // Generate observers
    'generate_middleware' => true,      // Generate middleware
    'generate_tests' => true,           // Generate tests
    'track_state_history' => true,     // Track state transitions
    
    'namespace' => 'App\\StateMachine', // Base namespace
    
    'paths' => [
        'traits' => 'app/StateMachine/Traits',
        'events' => 'app/StateMachine/Events',
        'observers' => 'app/StateMachine/Observers',
        'middleware' => 'app/StateMachine/Middleware',
        'tests' => 'tests/Feature/StateMachine',
    ],
];
```

## Examples

See the `examples/` directory for complete examples:

- `order-state-machine.yaml` - E-commerce order workflow
- `user-account-state-machine.yaml` - User account status management

## Testing

Run the extension tests:

```bash
composer test
```

## Plugin Architecture

This plugin uses Blueprint's new plugin system introduced in v2.0. The plugin consists of:

- **Plugin Class**: `BlueprintStateMachinePlugin` - Main plugin entry point
- **Lexer**: `StateMachineLexer` - Parses state machine syntax from YAML
- **Generator**: `StateMachineGenerator` - Generates state machine-related files
- **Service Provider**: `BlueprintStateMachineServiceProvider` - Laravel service provider

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This extension is open-sourced software licensed under the [MIT license](LICENSE). 
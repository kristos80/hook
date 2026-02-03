# 🪝 Hook

A lightweight, WordPress-style hooks system for PHP. Add actions and filters with priority support to create extensible,
event-driven applications.

---
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=bugs)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=coverage)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=kristos80_hook&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=kristos80_hook)

## Features

- ✅ WordPress-inspired API (`addAction`, `addFilter`, `doAction`, `applyFilter`)
- ✅ Priority-based execution order
- ✅ Multiple callbacks per hook
- ✅ Multiple hook names in a single call
- ✅ Supports all PHP callable types (closures, functions, static methods, instance methods, invokables)
- ✅ Optimized sorting (sorted once, cached until modified)
- ✅ Type-safe with strict types
- ✅ Interface-based design (`HookInterface`)
- ✅ Optional type hint enforcement for callbacks
- ✅ Zero dependencies

## Installation

```bash
composer require kristos80/hook
```

## Usage

### Basic Filter

```php
use Kristos80\Hook\Hook;

$hook = new Hook();

// Add a filter
$hook->addFilter('format_title', function(string $title) {
    return strtoupper($title);
});

// Apply the filter
$result = $hook->applyFilter('format_title', 'hello world');
echo $result; // HELLO WORLD
```

### Priority-based Execution

Lower priority numbers run first (default is 10):

```php
$hook->addFilter('modify_value', function(int $value) {
    return $value * 2;
}, 10);

$hook->addFilter('modify_value', function(int $value) {
    return $value + 5;
}, 5); // Runs first

$result = $hook->applyFilter('modify_value', 10);
echo $result; // 30 (first: 10 + 5 = 15, then: 15 * 2 = 30)
```

### Actions

Actions are filters that don't return values:

```php
$hook->addAction('user_login', function() {
    error_log('User logged in');
});

$hook->doAction('user_login');
```

### Multiple Arguments

```php
$hook->addFilter('format_name', function(string $name, string $prefix) {
    return $prefix . ' ' . $name;
});

$result = $hook->applyFilter('format_name', 'John', 'Mr.');
echo $result; // Mr. John
```

### Multiple Hook Names

Register the same callback to multiple hooks at once:

```php
$hook->addAction(['init', 'startup', 'boot'], function() {
    // Initialization logic
});

$hook->doAction('init');    // Executes callback
$hook->doAction('startup'); // Executes callback
$hook->doAction('boot');    // Executes callback
```

### Callable Types

The library accepts any valid PHP callable:

```php
// Closure
$hook->addFilter('my_filter', function(string $value) {
    return strtoupper($value);
});

// Function name (string)
$hook->addFilter('my_filter', 'strtoupper');

// Static method (string)
$hook->addFilter('my_filter', 'MyClass::transform');

// Static method (array)
$hook->addFilter('my_filter', [MyClass::class, 'transform']);

// Instance method (array)
$formatter = new TextFormatter();
$hook->addFilter('my_filter', [$formatter, 'format']);

// Invokable object
class MyTransformer {
    public function __invoke(string $value): string {
        return strtoupper($value);
    }
}
$hook->addFilter('my_filter', new MyTransformer());
```

### Enforcing Type Hints on Callbacks

Use the `requireTypedParameters` named argument to enforce that all callback parameters have type hints:

```php
$hook->addFilter('process_data', function(array $data): array {
    return array_map('strtoupper', $data);
});

// This will work - callback has typed parameters
$result = $hook->applyFilter('process_data', ['hello'], requireTypedParameters: true);

// Register an untyped callback
$hook->addFilter('other_filter', function($value) {
    return $value;
});

// This will throw MissingTypeHintException
$hook->applyFilter('other_filter', 'test', requireTypedParameters: true);
```

The `requireTypedParameters` argument is stripped and never passed to callbacks. This feature helps enforce stricter contracts when the hook owner wants to ensure all registered callbacks follow type safety conventions.

## API Reference

### `addFilter(string|array $hookNames, callable $callback, int $priority = 10): void`

Add a filter callback to one or more hooks.

- `$hookNames` - Hook name(s) to attach to
- `$callback` - Callable to execute
- `$priority` - Execution priority (lower = earlier, default: 10)

### `addAction(string|array $hookNames, callable $callback, int $priority = 10): void`

Alias for `addFilter()`. Use for hooks that don't return values.

> **Note:** The `$acceptedArgs` parameter exists for backwards compatibility but is deprecated and no longer used. PHP natively handles argument count validation.

### `applyFilter(string $hookName, ...$arg): mixed`

Execute all callbacks registered to a filter hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks
- `requireTypedParameters: bool` - Named argument to enforce type hints on callbacks (default: false)
- Returns the filtered value
- Throws `MissingTypeHintException` if `requireTypedParameters` is true and a callback has untyped parameters

### `doAction(string $hookName, ...$arg): void`

Execute all callbacks registered to an action hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks
- `requireTypedParameters: bool` - Named argument to enforce type hints on callbacks (default: false)
- Throws `MissingTypeHintException` if `requireTypedParameters` is true and a callback has untyped parameters

## Interface-based Design

The `Hook` class implements `HookInterface`, providing several benefits:

- **Dependency Injection** - Type-hint against `HookInterface` in your constructors and methods, making dependencies explicit and swappable
- **Testability** - Easily mock or stub the hook system in unit tests by creating test doubles that implement `HookInterface`
- **Decoupling** - Your code depends on an abstraction rather than a concrete implementation, following the Dependency Inversion Principle
- **Extensibility** - Create alternative implementations (e.g., a `NullHook` for disabled hooks, or a `LoggingHook` decorator) without modifying existing code
- **Contract Guarantee** - The interface defines a clear API contract, ensuring any implementation provides the expected methods

```php
// Type-hint against the interface for better architecture
class UserService {
    public function __construct(
        private HookInterface $hooks
    ) {}

    public function createUser(array $data): User {
        $data = $this->hooks->applyFilter('user_data', $data);
        // ...
    }
}
```

## Testing

```bash
./vendor/bin/pest
```

## License

MIT

## Author

Christos Athanasiadis - [chris.k.athanasiadis@gmail.com](mailto:chris.k.athanasiadis@gmail.com)

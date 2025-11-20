# Hook

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
- ✅ Optimized sorting (sorted once, cached until modified)
- ✅ Type-safe with strict types
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
}, 10, 2); // Accept 2 arguments

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

## API Reference

### `addFilter(string|array $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void`

Add a filter callback to one or more hooks.

- `$hookNames` - Hook name(s) to attach to
- `$callback` - Callable to execute
- `$priority` - Execution priority (lower = earlier, default: 10)
- `$acceptedArgs` - Number of arguments the callback accepts (default: 0)

### `addAction(string|array $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void`

Alias for `addFilter()`. Use for hooks that don't return values.

### `applyFilter(string $hookName, ...$arg): mixed`

Execute all callbacks registered to a filter hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks
- Returns the filtered value

### `doAction(string $hookName, ...$arg): void`

Execute all callbacks registered to an action hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks

## Testing

```bash
./vendor/bin/pest
```

## License

MIT

## Author

Christos Athanasiadis - [chris.k.athanasiadis@gmail.com](mailto:chris.k.athanasiadis@gmail.com)

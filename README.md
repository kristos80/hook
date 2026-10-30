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
- ✅ Deregistration (`removeAction`, `removeFilter`, `removeAllActions`, `removeAllFilters`)
- ✅ Priority-based execution order (with per-hook priority support)
- ✅ Multiple callbacks per hook
- ✅ Multiple hook names in a single call
- ✅ Supports all PHP callable types (closures, functions, static methods, instance methods, invokables)
- ✅ Optimized sorting (sorted once, cached until modified)
- ✅ Type-safe with strict types
- ✅ Interface-based design (`HookInterface`)
- ✅ Optional type hint enforcement for callbacks
- ✅ Priority introspection (`getMinPriority`, `getMaxPriority`)
- ✅ Registration and dispatch introspection (`hasFilter`, `didFilter`, `currentFilter`, `doingFilter`, plus `*Action` aliases)
- ✅ Built-in `FIRST` and `LAST` priority constants
- ✅ Zero dependencies

## Requirements

- PHP `^8.2`

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

### Per-Hook Priority

When registering multiple hook names, you can assign a different priority to each hook by passing an array of priorities. Each hook name is mapped to the priority at the same index, with a fallback to the first priority if the index doesn't exist:

```php
// Different priority per hook: 'init' gets priority 1, 'save' gets priority 20
$hook->addFilter(['init', 'save'], function(string $data) {
    return $data;
}, [1, 20]);

// Partial array: 'init' gets priority 5, 'save' and 'cleanup' fall back to index 0 (priority 5)
$hook->addAction(['init', 'save', 'cleanup'], function() {
    // ...
}, [5]);

// Single int still works as before — all hooks get the same priority
$hook->addFilter(['init', 'save'], $callback, 10);
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

### Priority Constants

Use `HookInterface::FIRST` and `HookInterface::LAST` to guarantee a callback runs before or after all others:

```php
use Kristos80\Hook\HookInterface;

// Guaranteed to run before any other callback
$hook->addFilter('process', function(string $data) {
    return trim($data);
}, HookInterface::FIRST);

// Guaranteed to run after any other callback
$hook->addFilter('process', function(string $data) {
    return htmlspecialchars($data);
}, HookInterface::LAST);
```

The constants are also accessible via `Hook::FIRST` and `Hook::LAST`. Multiple callbacks at the same constant priority follow FIFO order, consistent with the rest of the priority system.

### Priority Introspection

Query the lowest or highest registered priority for a given hook:

```php
$hook->addFilter('my_filter', function(string $v) { return $v; }, 5);
$hook->addFilter('my_filter', function(string $v) { return $v; }, 20);
$hook->addFilter('my_filter', function(string $v) { return $v; }, 12);

$hook->getMinPriority('my_filter'); // 5
$hook->getMaxPriority('my_filter'); // 20

// Returns null for hooks with no registered callbacks
$hook->getMinPriority('nonexistent'); // null
$hook->getMaxPriority('nonexistent'); // null
```

### Registration Introspection

`hasFilter()` / `hasAction()` answer "is anything listening?" and "is this exact callback attached?":

```php
$callback = function(string $value): string {
    return $value;
};

$hook->addFilter('my_filter', $callback, 5);

$hook->hasFilter('my_filter');                 // true  - something is attached
$hook->hasFilter('my_filter', $callback);      // true  - this callback, any priority
$hook->hasFilter('my_filter', $callback, 5);   // true  - this callback at priority 5
$hook->hasFilter('my_filter', $callback, 10);  // false - not at priority 10
$hook->hasFilter('my_filter', null, 5);        // true  - anything at priority 5
$hook->hasFilter('other_filter');              // false
```

Callbacks are matched by identity, exactly as in `removeFilter()`, which makes the two a natural pair:

```php
if ($hook->hasFilter('my_filter', $callback, 5)) {
    $hook->removeFilter('my_filter', $callback, 5);
}
```

### Dispatch Introspection

`didFilter()` / `didAction()` return how many times a hook has been dispatched. A hook counts as dispatched even when nothing was listening, and the count is not reset by removing callbacks:

```php
$hook->didAction('my_action');  // 0

$hook->doAction('my_action');
$hook->doAction('my_action');

$hook->didAction('my_action');  // 2
```

`currentFilter()` / `currentAction()` return the hook being dispatched right now — the innermost one when hooks are nested — and `doingFilter()` / `doingAction()` test the whole running stack:

```php
$hook->addAction('outer', function() use ($hook) {
    $hook->currentAction();        // 'outer'
    $hook->doAction('inner');
});

$hook->addAction('inner', function() use ($hook) {
    $hook->currentAction();        // 'inner'  - innermost only
    $hook->doingAction('inner');   // true
    $hook->doingAction('outer');   // true     - still on the stack
    $hook->doingAction();          // true     - any hook running?
});

$hook->doAction('outer');

$hook->currentAction();            // null     - stack unwound
$hook->doingAction();              // false
```

> **Note:** Because `doAction()` forwards to `applyFilter()`, the `*Action` and `*Filter` variants are aliases of one another throughout. A hook name dispatched both ways shares a single `didFilter()` / `didAction()` counter.

### Removing Callbacks

`removeFilter()` and `removeAction()` mirror their `add*` counterparts: by default they target priority `10`, so you must pass the same priority the callback was registered at.

```php
$callback = function(string $value): string {
    return strtoupper($value);
};

$hook->addFilter('my_filter', $callback, 5);

$hook->removeFilter('my_filter', $callback, 5); // true
$hook->removeFilter('my_filter', $callback, 5); // false - nothing left to remove
```

Callbacks are matched by identity (`===`), so you need a reference to the exact closure, object, or callable array that was registered. Keep a handle on anything you intend to remove later — an inline closure cannot be removed:

```php
// Not removable: no reference to the closure survives the call
$hook->addFilter('my_filter', fn(string $v): string => $v);
```

A `Class::method` string and a `[Class::class, 'method']` array are different callables for matching purposes; pass back the form you registered.

Pass `null` as the priority to remove a callback from **every** priority of a hook, without having to track where it was registered:

```php
$hook->addFilter('my_filter', $callback, 5);
$hook->addFilter('my_filter', $callback, 20);

$hook->removeFilter('my_filter', $callback, null); // true - both registrations gone
```

Like `addFilter()`, the removal methods accept a list of hook names with optional per-hook priorities:

```php
$hook->removeFilter(['hook_a', 'hook_b'], $callback, [5, 20]);

// Missing indices fall back to index 0, so hook_c is also removed at priority 5
$hook->removeFilter(['hook_a', 'hook_b', 'hook_c'], $callback, [5, 20]);
```

To clear a hook wholesale, use `removeAllFilters()` / `removeAllActions()`:

```php
$hook->removeAllFilters('my_filter');     // every priority
$hook->removeAllFilters('my_filter', 10); // only priority 10
$hook->removeAllActions('my_action');     // alias, for action hooks
```

All four methods return `true` when at least one registration was removed and `false` when there was nothing to remove.

> **Note:** Removing a callback while its hook is executing does not affect the in-flight dispatch — callbacks already queued for that run still execute. The removal takes effect on the next `applyFilter()` / `doAction()` call.

### Exceptions

Every exception the library throws extends the abstract `HookException`, so a single `catch (HookException $e)` will trap any hook-related failure:

```php
use Kristos80\Hook\HookException;
use Kristos80\Hook\CircularDependencyException;
use Kristos80\Hook\InvalidPriorityException;
use Kristos80\Hook\MissingTypeHintException;
```

| Exception                       | Thrown by                              | When                                                                                              |
| ------------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------------- |
| `CircularDependencyException`   | `applyFilter()` / `doAction()`         | A hook is dispatched while it is already on the running stack (a callback for `X` re-triggers `X`).|
| `InvalidPriorityException`      | `addFilter()` / `addAction()`          | A resolved priority is not an integer.                                                            |
| `InvalidPriorityException`      | `removeFilter()` / `removeAction()`    | A resolved priority is neither an integer nor `null`.                                             |
| `MissingTypeHintException`      | `applyFilter()` / `doAction()`         | `requireTypedParameters: true` was passed and a registered callback has an untyped parameter.     |
| `HookException` (abstract base) | —                                      | Never thrown directly; use it as a catch-all supertype.                                           |

## API Reference

### `addFilter(string|array $hookNames, callable $callback, int|array $priority = 10): void`

Add a filter callback to one or more hooks.

- `$hookNames` - Hook name(s) to attach to
- `$callback` - Callable to execute
- `$priority` - Execution priority (lower = earlier, default: 10). Pass an array to assign a different priority per hook name (falls back to index 0 for missing indices)
- Throws `InvalidPriorityException` if a resolved priority is not an integer

### `addAction(string|array $hookNames, callable $callback, int|array $priority = 10): void`

Alias for `addFilter()`. Use for hooks that don't return values.

> **Note:** The `$acceptedArgs` parameter exists for backwards compatibility but is deprecated and no longer used. PHP natively handles argument count validation.

### `removeFilter(string|array $hookNames, callable $callback, int|array|null $priority = 10): bool`

Remove a previously registered filter callback from one or more hooks.

- `$hookNames` - Hook name(s) to remove the callback from
- `$callback` - The callable to remove, matched by identity (`===`)
- `$priority` - Priority the callback was registered at (default: 10). Pass `null` to remove it from every priority of the hook. Pass an array to assign a different priority per hook name (falls back to index 0 for missing indices)
- Returns `true` when at least one registration was removed, `false` otherwise
- Throws `InvalidPriorityException` if a resolved priority is neither an integer nor `null`

Every matching registration inside a targeted priority is removed, so a callback registered twice at the same priority is cleared by a single call.

### `removeAction(string|array $hookNames, callable $callback, int|array|null $priority = 10): bool`

Alias for `removeFilter()`. Use for hooks registered with `addAction()`.

### `removeAllFilters(string $hookName, ?int $priority = null): bool`

Remove every filter callback registered under a hook.

- `$hookName` - Hook name to clear
- `$priority` - When given, only this priority is cleared; when `null` (default), the entire hook is cleared
- Returns `true` when at least one registration was removed, `false` otherwise

### `removeAllActions(string $hookName, ?int $priority = null): bool`

Alias for `removeAllFilters()`. Use for hooks registered with `addAction()`.

### `applyFilter(string $hookName, ...$arg): mixed`

Execute all callbacks registered to a filter hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks
- `requireTypedParameters: bool` - Named argument to enforce type hints on callbacks (default: false)
- Returns the filtered value
- Throws `CircularDependencyException` if the hook is already on the running stack
- Throws `MissingTypeHintException` if `requireTypedParameters` is true and a callback has untyped parameters

### `doAction(string $hookName, ...$arg): void`

Execute all callbacks registered to an action hook.

- `$hookName` - Hook name to execute
- `...$arg` - Arguments to pass to callbacks
- `requireTypedParameters: bool` - Named argument to enforce type hints on callbacks (default: false)
- Throws `CircularDependencyException` if the hook is already on the running stack
- Throws `MissingTypeHintException` if `requireTypedParameters` is true and a callback has untyped parameters

### `HookInterface::FIRST` / `HookInterface::LAST`

Priority constants for guaranteed earliest (`PHP_INT_MIN`) and latest (`PHP_INT_MAX`) execution. Also accessible as `Hook::FIRST` / `Hook::LAST`.

### `getMinPriority(string $hookName): ?int`

Get the lowest registered priority for a hook.

- `$hookName` - Hook name to query
- Returns the minimum priority value, or `null` if the hook has no registered callbacks

### `getMaxPriority(string $hookName): ?int`

Get the highest registered priority for a hook.

- `$hookName` - Hook name to query
- Returns the maximum priority value, or `null` if the hook has no registered callbacks

### `hasFilter(string $hookName, ?callable $callback = null, ?int $priority = null): bool`

Check whether a hook has callbacks registered.

- `$hookName` - Hook name to inspect
- `$callback` - When given, only this callable is looked for, matched by identity (`===`); when `null`, any registered callback satisfies the check
- `$priority` - When given, only this priority is inspected; when `null` (default), every priority is inspected
- Returns `true` when a matching registration exists

### `hasAction(string $hookName, ?callable $callback = null, ?int $priority = null): bool`

Alias for `hasFilter()`.

### `didFilter(string $hookName): int`

Number of times a hook has been dispatched. Counts dispatches with no registered callbacks, and is not reset by removing callbacks.

- `$hookName` - Hook name to inspect
- Returns the dispatch count, or `0` if the hook has never been dispatched

### `didAction(string $hookName): int`

Alias for `didFilter()`.

### `currentFilter(): ?string`

The hook currently being dispatched; the innermost one when hooks are nested.

- Returns the running hook name, or `null` when no hook is being dispatched

### `currentAction(): ?string`

Alias for `currentFilter()`.

### `doingFilter(?string $hookName = null): bool`

Whether a hook is currently being dispatched. Unlike `currentFilter()`, this matches outer hooks of a nested dispatch too.

- `$hookName` - When given, tests whether this hook is on the running stack; when `null` (default), tests whether any hook is being dispatched
- Returns `true` when the hook (or any hook) is being dispatched

### `doingAction(?string $hookName = null): bool`

Alias for `doingFilter()`.

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

## Testing & Static Analysis

The project is held to three gates that all pass at 100%:

```bash
# Unit tests
./vendor/bin/pest

# Line coverage (must be 100%)
herd coverage ./vendor/bin/pest --coverage

# Mutation testing (must be 100%)
herd coverage ./vendor/bin/pest --mutate --parallel --everything --covered-only

# Static analysis at PHPStan level max
./vendor/bin/phpstan analyse
```

Substitute `herd coverage` with your own coverage driver (Xdebug, PCOV) if you are not on Laravel Herd.

## License

MIT

## Author

Christos Athanasiadis - [chris.k.athanasiadis@gmail.com](mailto:chris.k.athanasiadis@gmail.com)

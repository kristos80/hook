<?php
declare(strict_types=1);

namespace Kristos80\Hook;

/**
 * Public contract for a WordPress-style hook system supporting actions and filters
 * with integer priorities. Implementations must invoke callbacks in ascending priority
 * order and detect circular dependencies at dispatch time.
 */
interface HookInterface {

	/**
	 * Convenience alias for "run before everything else"; the lowest possible priority value.
	 */
	public const FIRST = PHP_INT_MIN;

	/**
	 * Convenience alias for "run after everything else"; the highest possible priority value.
	 */
	public const LAST = PHP_INT_MAX;

	/**
	 * Registers an action callback whose return value will be discarded by {@see self::doAction()}.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        register the same callback against.
	 * @param callable $callback The callback to invoke when the hook fires.
	 * @param array<int, int>|int $priority Execution priority (lower runs first). When
	 *        $hookNames is a list, priorities may be passed positionally; missing entries fall
	 *        back to the first priority in the array.
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility.
	 * @return void
	 */
	public function addAction(array|string $hookNames,
		callable $callback,
		array|int $priority = 10,
		int $acceptedArgs = 0): void;

	/**
	 * Registers a filter callback that can transform the value passed through {@see self::applyFilter()}.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        register the same callback against.
	 * @param callable $callback The callback to invoke when the hook fires; its first argument
	 *        receives the filtered value.
	 * @param array<int, int>|int $priority Execution priority (lower runs first). When
	 *        $hookNames is a list, priorities may be passed positionally; missing entries fall
	 *        back to the first priority in the array.
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility.
	 * @return void
	 */
	public function addFilter(array|string $hookNames,
		callable $callback,
		array|int $priority = 10,
		int $acceptedArgs = 0): void;

	/**
	 * Removes a previously registered action callback.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        remove the callback from.
	 * @param callable $callback The callback to remove, matched by identity (`===`).
	 * @param array<int, int|null>|int|null $priority Priority the callback was registered at
	 *        (defaults to 10). Pass null to remove it from every priority of the hook. When
	 *        $hookNames is a list, priorities may be passed positionally; missing entries fall
	 *        back to the first priority in the array.
	 * @return bool True when at least one registration was removed.
	 * @throws InvalidPriorityException When a resolved priority is neither an integer nor null.
	 */
	public function removeAction(array|string $hookNames,
		callable $callback,
		array|int|null $priority = 10): bool;

	/**
	 * Removes a previously registered filter callback.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        remove the callback from.
	 * @param callable $callback The callback to remove, matched by identity (`===`).
	 * @param array<int, int|null>|int|null $priority Priority the callback was registered at
	 *        (defaults to 10). Pass null to remove it from every priority of the hook. When
	 *        $hookNames is a list, priorities may be passed positionally; missing entries fall
	 *        back to the first priority in the array.
	 * @return bool True when at least one registration was removed.
	 * @throws InvalidPriorityException When a resolved priority is neither an integer nor null.
	 */
	public function removeFilter(array|string $hookNames,
		callable $callback,
		array|int|null $priority = 10): bool;

	/**
	 * Removes every action callback registered under a hook, or only those at a single priority.
	 *
	 * @param string $hookName The hook to clear.
	 * @param int|null $priority When given, only this priority bucket is cleared; when null, the
	 *        entire hook is cleared.
	 * @return bool True when at least one registration was removed.
	 */
	public function removeAllActions(string $hookName, ?int $priority = null): bool;

	/**
	 * Removes every filter callback registered under a hook, or only those at a single priority.
	 *
	 * @param string $hookName The hook to clear.
	 * @param int|null $priority When given, only this priority bucket is cleared; when null, the
	 *        entire hook is cleared.
	 * @return bool True when at least one registration was removed.
	 */
	public function removeAllFilters(string $hookName, ?int $priority = null): bool;

	/**
	 * Executes all callbacks registered under $hookName, discarding any return value.
	 *
	 * @param string $hookName The hook to fire.
	 * @param mixed ...$arg Arguments forwarded to each callback. Pass `requireTypedParameters: true`
	 *        as a named argument to enforce type hints on callbacks.
	 * @return void
	 * @throws CircularDependencyException When the hook is already on the running stack.
	 * @throws MissingTypeHintException When strict typing is requested and a parameter is untyped.
	 */
	public function doAction(string $hookName, ...$arg): void;

	/**
	 * Executes all callbacks registered under $hookName and returns the filtered value.
	 *
	 * @param string $hookName The hook to fire.
	 * @param mixed ...$arg Arguments forwarded to each callback. Pass `requireTypedParameters: true`
	 *        as a named argument to enforce type hints on callbacks.
	 * @return mixed The value returned by the last callback, or the initial value when no
	 *         callbacks are registered.
	 * @throws CircularDependencyException When the hook is already on the running stack.
	 * @throws MissingTypeHintException When strict typing is requested and a parameter is untyped.
	 */
	public function applyFilter(string $hookName, ...$arg): mixed;

	/**
	 * Returns the lowest priority registered for a hook, or null when no callbacks are registered.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int|null
	 */
	public function getMinPriority(string $hookName): ?int;

	/**
	 * Returns the highest priority registered for a hook, or null when no callbacks are registered.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int|null
	 */
	public function getMaxPriority(string $hookName): ?int;
}

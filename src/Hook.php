<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;

final class Hook implements HookInterface {

	/**
	 * Internal array key that holds the actual callable inside a single callback entry.
	 */
	private const CALLBACK = "callback";

	/**
	 * Registered hook callbacks, keyed by hook name and then by integer priority. The
	 * innermost value is a list of entries so that multiple callbacks may share the same
	 * priority within a single hook.
	 *
	 * @var array<string, array<int, list<array{callback: callable}>>>
	 */
	private array $filters = [];

	/**
	 * Marker set recording which hooks have already had their priority buckets ksorted.
	 * Presence of a key means "sorted, do not re-sort"; {@see self::addFilter()} unsets
	 * the entry to invalidate the sort when a new callback is registered.
	 *
	 * @var array<string, true>
	 */
	private array $sortedHooks = [];

	/**
	 * Stack of hook names currently being executed. Used to detect circular dependencies
	 * when a hook's callback triggers the same hook (directly or transitively), and read by
	 * {@see self::currentFilter()} and {@see self::doingFilter()}.
	 *
	 * @var list<string>
	 */
	private array $runningHooks = [];

	/**
	 * Number of times each hook has been dispatched, keyed by hook name. Incremented by
	 * {@see self::applyFilter()} before any early return, so a hook counts as fired whether or
	 * not anything was listening.
	 *
	 * @var array<string, int>
	 */
	private array $hookRunCounts = [];

	/**
	 * Cache of string callables (function names and "Class::method" strings) already
	 * verified by {@see self::validateCallbackTypeHints()}, used to short-circuit reflection.
	 *
	 * @var array<string, true>
	 */
	private array $validatedStringCallbacks = [];

	/**
	 * Cache of [class, method] callables already verified, indexed by class name then method name.
	 *
	 * @var array<string, array<string, true>>
	 */
	private array $validatedClassMethods = [];

	/**
	 * Cache of invokable objects already verified, indexed by {@see spl_object_id()}.
	 *
	 * @var array<int, true>
	 */
	private array $validatedObjects = [];

	/**
	 * Registers an action callback. Actions are filters whose return value is discarded by
	 * {@see self::doAction()}; this method simply forwards to {@see self::addFilter()}.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names
	 *        to register the same callback against.
	 * @param callable $callback The callback to invoke when the hook fires.
	 * @param array<int, int>|int $priority Execution priority (lower runs first). When
	 *        $hookNames is a list, an array of priorities may be passed positionally; missing
	 *        entries fall back to the first priority in the array.
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility.
	 * @return void
	 * @throws InvalidPriorityException When a resolved priority is not an integer.
	 */
	public function addAction(array|string $hookNames,
		callable $callback,
		array|int $priority = 10,
		int $acceptedArgs = 0): void {
		$this->addFilter($hookNames, $callback, $priority);
	}

	/**
	 * Registers a filter callback under one or more hook names.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names
	 *        to register the same callback against.
	 * @param callable $callback The callback to invoke when the hook fires. Its first argument
	 *        receives the filtered value; subsequent arguments are forwarded from the caller.
	 * @param array<int, int>|int $priority Execution priority (lower runs first). When
	 *        $hookNames is a list, an array of priorities may be passed positionally; missing
	 *        entries fall back to the first priority in the array.
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility.
	 * @return void
	 * @throws InvalidPriorityException When a resolved priority is not an integer.
	 */
	public function addFilter(array|string $hookNames,
		callable $callback,
		array|int $priority = 10,
		int $acceptedArgs = 0): void {
		if(is_string($hookNames)) {
			$hookNames = [$hookNames];
		}

		if(!is_array($priority)) {
			$priority = [$priority];
		}

		foreach($hookNames as $index => $hookName) {
			$hookPriority = $priority[$index] ?? $priority[0];
			if(!is_int($hookPriority)) {
				throw new InvalidPriorityException("Priority must be an integer");
			}
			$this->filters[$hookName][$hookPriority][] = [
				self::CALLBACK => $callback,
			];
			unset($this->sortedHooks[$hookName]);
		}
	}

	/**
	 * Removes a previously registered action callback. Actions are stored as filters, so this
	 * method simply forwards to {@see self::removeFilter()}.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        remove the callback from.
	 * @param callable $callback The callback to remove. Matched by identity (`===`), so the same
	 *        closure instance, the same object, or an equal "Class::method"/[class, method]
	 *        callable must be passed; a `Class::method` string does not match a [class, method]
	 *        array registration.
	 * @param array<int, int|null>|int|null $priority Priority the callback was registered at
	 *        (defaults to 10, matching {@see self::addAction()}). Pass null to remove the
	 *        callback from every priority of the hook. When $hookNames is a list, priorities may
	 *        be passed positionally; missing entries fall back to the first priority in the array.
	 * @return bool True when at least one registration was removed.
	 * @throws InvalidPriorityException When a resolved priority is neither an integer nor null.
	 */
	public function removeAction(array|string $hookNames,
		callable $callback,
		array|int|null $priority = 10): bool {
		return $this->removeFilter($hookNames, $callback, $priority);
	}

	/**
	 * Removes a previously registered filter callback from one or more hook names.
	 *
	 * Every matching registration inside a targeted priority bucket is removed, so a callback
	 * registered twice at the same priority is cleared by a single call. Removing a callback
	 * while its hook is executing does not affect the in-flight dispatch; the change takes
	 * effect on the next {@see self::applyFilter()} call.
	 *
	 * @param array<int, string>|string $hookNames Single hook name, or a list of hook names to
	 *        remove the callback from.
	 * @param callable $callback The callback to remove. Matched by identity (`===`), so the same
	 *        closure instance, the same object, or an equal "Class::method"/[class, method]
	 *        callable must be passed; a `Class::method` string does not match a [class, method]
	 *        array registration.
	 * @param array<int, int|null>|int|null $priority Priority the callback was registered at
	 *        (defaults to 10, matching {@see self::addFilter()}). Pass null to remove the
	 *        callback from every priority of the hook. When $hookNames is a list, priorities may
	 *        be passed positionally; missing entries fall back to the first priority in the array.
	 * @return bool True when at least one registration was removed.
	 * @throws InvalidPriorityException When a resolved priority is neither an integer nor null.
	 */
	public function removeFilter(array|string $hookNames,
		callable $callback,
		array|int|null $priority = 10): bool {
		if(is_string($hookNames)) {
			$hookNames = [$hookNames];
		}

		if(!is_array($priority)) {
			$priority = [$priority];
		}

		$removed = FALSE;

		foreach($hookNames as $index => $hookName) {
			$hookPriority = array_key_exists($index, $priority) ? $priority[$index] : $priority[0];
			if($hookPriority !== null && !is_int($hookPriority)) {
				throw new InvalidPriorityException("Priority must be an integer or null");
			}

			if(!isset($this->filters[$hookName])) {
				continue;
			}

			$priorities = $hookPriority === null
				? array_keys($this->filters[$hookName])
				: [$hookPriority];

			foreach($priorities as $hookPriorityKey) {
				$kept = [];
				foreach($this->filters[$hookName][$hookPriorityKey] ?? [] as $entry) {
					if($entry[self::CALLBACK] === $callback) {
						$removed = TRUE;
						continue;
					}
					$kept[] = $entry;
				}

				if($kept === []) {
					unset($this->filters[$hookName][$hookPriorityKey]);
					continue;
				}

				$this->filters[$hookName][$hookPriorityKey] = $kept;
			}

			$this->forgetEmptyHook($hookName);
		}

		return $removed;
	}

	/**
	 * Removes every action callback registered under a hook, or only those at a single priority.
	 * Actions are stored as filters, so this method simply forwards to
	 * {@see self::removeAllFilters()}.
	 *
	 * @param string $hookName The hook to clear.
	 * @param int|null $priority When given, only this priority bucket is cleared; when null, the
	 *        entire hook is cleared.
	 * @return bool True when at least one registration was removed.
	 */
	public function removeAllActions(string $hookName, ?int $priority = null): bool {
		return $this->removeAllFilters($hookName, $priority);
	}

	/**
	 * Removes every filter callback registered under a hook, or only those at a single priority.
	 *
	 * @param string $hookName The hook to clear.
	 * @param int|null $priority When given, only this priority bucket is cleared; when null, the
	 *        entire hook is cleared.
	 * @return bool True when at least one registration was removed.
	 */
	public function removeAllFilters(string $hookName, ?int $priority = null): bool {
		if(!isset($this->filters[$hookName])) {
			return FALSE;
		}

		if($priority === null) {
			unset($this->filters[$hookName], $this->sortedHooks[$hookName]);

			return TRUE;
		}

		if(!isset($this->filters[$hookName][$priority])) {
			return FALSE;
		}

		unset($this->filters[$hookName][$priority]);
		$this->forgetEmptyHook($hookName);

		return TRUE;
	}

	/**
	 * Drops a hook's bookkeeping once its last callback has been removed, so that an emptied
	 * hook is indistinguishable from one that was never registered.
	 *
	 * @param string $hookName The hook to prune.
	 * @return void
	 */
	private function forgetEmptyHook(string $hookName): void {
		if($this->filters[$hookName] === []) {
			unset($this->filters[$hookName], $this->sortedHooks[$hookName]);
		}
	}

	/**
	 * Executes all callbacks registered under $hookName, discarding any return value.
	 *
	 * @param string $hookName The hook to fire.
	 * @param mixed ...$arg Arguments forwarded to each callback. Pass the named argument
	 *        `requireTypedParameters: true` to enforce that every callback declares a type
	 *        hint for each of its parameters.
	 * @return void
	 * @throws CircularDependencyException When the same hook is already on the running stack.
	 * @throws MissingTypeHintException When strict typing is requested and a parameter is untyped.
	 * @throws ReflectionException When reflection on a callback fails.
	 */
	public function doAction(string $hookName, ...$arg): void {
		$this->applyFilter($hookName, ...$arg);
	}

	/**
	 * Executes all callbacks registered under $hookName and returns the filtered value.
	 * The first positional argument seeds the filtered value; each callback receives the
	 * running result plus any remaining arguments and returns the next result.
	 *
	 * @param string $hookName The hook to fire.
	 * @param mixed ...$arg Arguments forwarded to each callback. Pass the named argument
	 *        `requireTypedParameters: true` to enforce that every callback declares a type
	 *        hint for each of its parameters.
	 * @return mixed The value returned by the last callback, or the initial value when no
	 *         callbacks are registered.
	 * @throws CircularDependencyException When the same hook is already on the running stack.
	 * @throws MissingTypeHintException When strict typing is requested and a parameter is untyped.
	 * @throws ReflectionException When reflection on a callback fails.
	 */
	public function applyFilter(string $hookName, ...$arg): mixed {
		$this->hookRunCounts[$hookName] = ($this->hookRunCounts[$hookName] ?? 0) + 1;

		$requireTypedParameters = FALSE;
		if(array_key_exists("requireTypedParameters", $arg)) {
			$requireTypedParameters = $arg["requireTypedParameters"];
			unset($arg["requireTypedParameters"]);
		}

		if(!($this->filters[$hookName] ?? null)) {
			return $arg[0] ?? null;
		}

		if(in_array($hookName, $this->runningHooks)) {
			throw new CircularDependencyException("Circular Dependency for '$hookName'");
		}

		if(!isset($this->sortedHooks[$hookName])) {
			ksort($this->filters[$hookName]);
			$this->sortedHooks[$hookName] = TRUE;
		}

		$this->runningHooks[] = $hookName;

		$runOnce = FALSE;
		$result = null;

		foreach($this->filters[$hookName] as $priority) {
			foreach($priority as $hook) {
				if($requireTypedParameters) {
					$this->validateCallbackTypeHints($hook[self::CALLBACK], $hookName);
				}

				if(!$runOnce) {
					$result = array_shift($arg);
					$runOnce = TRUE;
				}

				/**
				 * @var mixed $result
				 */
				$result = $hook[self::CALLBACK]($result, ...$arg);
			}
		}

		array_pop($this->runningHooks);

		return $result;
	}

	/**
	 * Reflects the callback and asserts that every parameter has an explicit type hint.
	 * Results are cached per string callable, per class::method, and per invokable object id
	 * so repeated invocations do not pay the reflection cost.
	 *
	 * @param callable $callback The callback to inspect.
	 * @param string $hookName The hook name, used for error messages.
	 * @return void
	 * @throws MissingTypeHintException When any parameter lacks a declared type.
	 * @throws ReflectionException When reflection on the callback fails.
	 */
	private function validateCallbackTypeHints(callable $callback, string $hookName): void {
		if(is_string($callback)) {
			if(isset($this->validatedStringCallbacks[$callback])) {
				return;
			}
			// The single-argument ReflectionMethod constructor is deprecated as of PHP 8.4, and
			// its replacement (ReflectionMethod::createFromMethodName()) does not exist before
			// 8.4, so split the "Class::method" string and use the two-argument form, which is
			// valid on every supported version.
			$separatorPosition = strpos($callback, "::");
			$reflection = $separatorPosition === FALSE
				? new ReflectionFunction($callback)
				: new ReflectionMethod(
					substr($callback, 0, $separatorPosition),
					substr($callback, $separatorPosition + 2),
				);
			$this->assertAllParametersTyped($reflection, $hookName);
			$this->validatedStringCallbacks[$callback] = TRUE;
			return;
		}

		if(is_array($callback)) {
			$class = is_object($callback[0]) ? $callback[0]::class : $callback[0];
			if(isset($this->validatedClassMethods[$class][$callback[1]])) {
				return;
			}
			$this->assertAllParametersTyped(
				new ReflectionMethod($callback[0], $callback[1]),
				$hookName,
			);
			$this->validatedClassMethods[$class][$callback[1]] = TRUE;
			return;
		}

		/** @var callable&object $callback */
		$id = spl_object_id($callback);
		if(isset($this->validatedObjects[$id])) {
			return;
		}
		$this->assertAllParametersTyped(
			new ReflectionMethod($callback, "__invoke"),
			$hookName,
		);
		$this->validatedObjects[$id] = TRUE;
	}

	/**
	 * Throws when any parameter of the reflected callable lacks a declared type hint.
	 *
	 * @param ReflectionFunction|ReflectionMethod $reflection Reflection object for the callable.
	 * @param string $hookName The hook name, used for error messages.
	 * @return void
	 * @throws MissingTypeHintException When any parameter has no type declaration.
	 */
	private function assertAllParametersTyped(
		ReflectionFunction|ReflectionMethod $reflection,
		string $hookName,
	): void {
		foreach($reflection->getParameters() as $parameter) {
			if($parameter->getType() === null) {
				throw new MissingTypeHintException(
					"Callback for hook '$hookName' has parameter '\${$parameter->getName()}' without a type hint",
				);
			}
		}
	}

	/**
	 * Returns the lowest priority currently registered for a hook (i.e. the one that would
	 * execute first), or null if the hook has no callbacks.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int|null
	 */
	public function getMinPriority(string $hookName): ?int {
		$priorities = array_keys($this->filters[$hookName] ?? []);
		if($priorities === []) {
			return null;
		}

		return min($priorities);
	}

	/**
	 * Returns the highest priority currently registered for a hook (i.e. the one that would
	 * execute last), or null if the hook has no callbacks.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int|null
	 */
	public function getMaxPriority(string $hookName): ?int {
		$priorities = array_keys($this->filters[$hookName] ?? []);
		if($priorities === []) {
			return null;
		}

		return max($priorities);
	}

	/**
	 * Reports whether an action hook has any callback registered, or whether one specific
	 * callback is registered. Actions are stored as filters, so this method simply forwards to
	 * {@see self::hasFilter()}.
	 *
	 * @param string $hookName The hook to inspect.
	 * @param callable|null $callback When given, only this callback is looked for, matched by
	 *        identity (`===`); when null, any registered callback satisfies the check.
	 * @param int|null $priority When given, only this priority is inspected; when null, every
	 *        priority of the hook is inspected.
	 * @return bool
	 */
	public function hasAction(string $hookName, ?callable $callback = null, ?int $priority = null): bool {
		return $this->hasFilter($hookName, $callback, $priority);
	}

	/**
	 * Reports whether a filter hook has any callback registered, or whether one specific
	 * callback is registered.
	 *
	 * @param string $hookName The hook to inspect.
	 * @param callable|null $callback When given, only this callback is looked for, matched by
	 *        identity (`===`) exactly as in {@see self::removeFilter()}; when null, any
	 *        registered callback satisfies the check.
	 * @param int|null $priority When given, only this priority is inspected; when null, every
	 *        priority of the hook is inspected.
	 * @return bool
	 */
	public function hasFilter(string $hookName, ?callable $callback = null, ?int $priority = null): bool {
		if(!isset($this->filters[$hookName])) {
			return FALSE;
		}

		$priorities = $priority === null
			? array_keys($this->filters[$hookName])
			: [$priority];

		foreach($priorities as $hookPriorityKey) {
			$entries = $this->filters[$hookName][$hookPriorityKey] ?? [];

			if($callback === null && $entries !== []) {
				return TRUE;
			}

			foreach($entries as $entry) {
				if($entry[self::CALLBACK] === $callback) {
					return TRUE;
				}
			}
		}

		return FALSE;
	}

	/**
	 * Returns how many times an action hook has been dispatched. Actions are stored as filters,
	 * so this method simply forwards to {@see self::didFilter()}.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int Zero when the hook has never been dispatched.
	 */
	public function didAction(string $hookName): int {
		return $this->didFilter($hookName);
	}

	/**
	 * Returns how many times a filter hook has been dispatched. A hook counts as dispatched even
	 * when nothing was registered for it, and the count is not reset by removing callbacks.
	 * Because {@see self::doAction()} forwards to {@see self::applyFilter()}, action and filter
	 * dispatches of the same hook name share a single counter.
	 *
	 * @param string $hookName The hook to inspect.
	 * @return int Zero when the hook has never been dispatched.
	 */
	public function didFilter(string $hookName): int {
		return $this->hookRunCounts[$hookName] ?? 0;
	}

	/**
	 * Returns the action hook currently being dispatched. Actions are stored as filters, so this
	 * method simply forwards to {@see self::currentFilter()}.
	 *
	 * @return string|null The innermost running hook name, or null when none is running.
	 */
	public function currentAction(): ?string {
		return $this->currentFilter();
	}

	/**
	 * Returns the filter hook currently being dispatched. When hooks are nested, the innermost
	 * one is returned.
	 *
	 * @return string|null The innermost running hook name, or null when none is running.
	 */
	public function currentFilter(): ?string {
		$innermost = array_key_last($this->runningHooks);

		return $innermost === null ? null : $this->runningHooks[$innermost];
	}

	/**
	 * Reports whether an action hook is currently being dispatched. Actions are stored as
	 * filters, so this method simply forwards to {@see self::doingFilter()}.
	 *
	 * @param string|null $hookName When given, reports whether this specific hook is on the
	 *        running stack; when null, reports whether any hook is being dispatched.
	 * @return bool
	 */
	public function doingAction(?string $hookName = null): bool {
		return $this->doingFilter($hookName);
	}

	/**
	 * Reports whether a filter hook is currently being dispatched. Unlike
	 * {@see self::currentFilter()}, this also matches outer hooks of a nested dispatch.
	 *
	 * @param string|null $hookName When given, reports whether this specific hook is on the
	 *        running stack; when null, reports whether any hook is being dispatched.
	 * @return bool
	 */
	public function doingFilter(?string $hookName = null): bool {
		if($hookName === null) {
			return $this->runningHooks !== [];
		}

		return in_array($hookName, $this->runningHooks, TRUE);
	}
}

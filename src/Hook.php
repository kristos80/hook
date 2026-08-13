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
	 * when a hook's callback triggers the same hook (directly or transitively).
	 *
	 * @var list<string>
	 */
	private array $runningHooks = [];

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
			$reflection = str_contains($callback, "::")
				? new ReflectionMethod($callback)
				: new ReflectionFunction($callback);
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
}

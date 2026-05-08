<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;

final class Hook implements HookInterface {

	/**
	 *
	 */
	private const CALLBACKS = "callbacks";

	/**
	 *
	 */
	private const CALLBACK = "callback";

	/**
	 *
	 */
	private const SORTED = "sorted";

	/**
	 * @var array
	 */
	private array $filters = [];

	/**
	 * @var array
	 */
	private array $runningHooks = [];

	/**
	 * @var array<string, true>
	 */
	private array $validatedStringCallbacks = [];

	/**
	 * @var array<string, array<string, true>>
	 */
	private array $validatedClassMethods = [];

	/**
	 * @var array<int, true>
	 */
	private array $validatedObjects = [];

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param array|int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
	 */
	public function addAction(array|string $hookNames,
		callable $callback,
		array|int $priority = 10,
		int $acceptedArgs = 0): void {
		$this->addFilter($hookNames, $callback, $priority);
	}

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param array|int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
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
			$this->filters[$hookName] = $this->filters[$hookName] ?? [];
			$this->filters[$hookName][self::CALLBACKS][$hookPriority] =
				$this->filters[$hookName][self::CALLBACKS][$hookPriority] ?? [];
			$this->filters[$hookName][self::CALLBACKS][$hookPriority][] = [
				self::CALLBACK => $callback,
			];
			$this->filters[$hookName][self::SORTED] = FALSE;
		}
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return void
	 * @throws CircularDependencyException
	 * @throws MissingTypeHintException
	 * @throws ReflectionException
	 */
	public function doAction(string $hookName, ...$arg): void {
		$this->applyFilter($hookName, ...$arg);
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return mixed
	 * @throws CircularDependencyException
	 * @throws MissingTypeHintException
	 * @throws ReflectionException
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

		if(!$this->filters[$hookName][self::SORTED]) {
			ksort($this->filters[$hookName][self::CALLBACKS]);
			$this->filters[$hookName][self::SORTED] = TRUE;
		}

		$this->runningHooks[] = $hookName;

		$runOnce = FALSE;
		$result = null;

		foreach($this->filters[$hookName][self::CALLBACKS] as $priority) {
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
	 * @param callable $callback
	 * @param string $hookName
	 * @return void
	 * @throws MissingTypeHintException
	 * @throws ReflectionException
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
	 * @param ReflectionFunction|ReflectionMethod $reflection
	 * @param string $hookName
	 * @return void
	 * @throws MissingTypeHintException
	 */
	private function assertAllParametersTyped(
		ReflectionFunction|ReflectionMethod $reflection,
		string $hookName,
	): void {
		foreach($reflection->getParameters() as $parameter) {
			if($parameter->getType() === NULL) {
				throw new MissingTypeHintException(
					"Callback for hook '$hookName' has parameter '\${$parameter->getName()}' without a type hint",
				);
			}
		}
	}

	/**
	 * @param string $hookName
	 * @return int|null
	 */
	public function getMinPriority(string $hookName): ?int {
		if(!($this->filters[$hookName][self::CALLBACKS] ?? null)) {
			return null;
		}

		return min(array_keys($this->filters[$hookName][self::CALLBACKS]));
	}

	/**
	 * @param string $hookName
	 * @return int|null
	 */
	public function getMaxPriority(string $hookName): ?int {
		if(!($this->filters[$hookName][self::CALLBACKS] ?? null)) {
			return null;
		}

		return max(array_keys($this->filters[$hookName][self::CALLBACKS]));
	}
}

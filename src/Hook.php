<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use ReflectionMethod;
use ReflectionFunction;
use ReflectionException;

/**
 * Class Hook
 *
 * @package Kristos80\Hook
 * @author Christos Athanasiadis <chris.k.athanasiadis@gmail.com>
 * @date 3/10/25
 */
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
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
	 */
	public function addAction(array|string $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void {
		$this->addFilter($hookNames, $callback, $priority);
	}

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
	 */
	public function addFilter(array|string $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void {
		if(is_string($hookNames)) {
			$hookNames = [$hookNames];
		}

		foreach($hookNames as $hookName) {
			$this->filters[$hookName] = $this->filters[$hookName] ?? [];
			$this->filters[$hookName][self::CALLBACKS][$priority] = $this->filters[$hookName][self::CALLBACKS][$priority] ?? [];
			$this->filters[$hookName][self::CALLBACKS][$priority][] = [
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
	 */
	public function applyFilter(string $hookName, ...$arg): mixed {
		$requireTypedParameters = FALSE;
		if(array_key_exists("requireTypedParameters", $arg)) {
			$requireTypedParameters = $arg["requireTypedParameters"];
			unset($arg["requireTypedParameters"]);
		}

		if(!($this->filters[$hookName] ?? NULL)) {
			return $arg[0] ?? NULL;
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
		$result = NULL;

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
		$reflection = $this->getCallableReflection($callback);
		$parameters = $reflection->getParameters();

		foreach($parameters as $parameter) {
			if($parameter->getType() === NULL) {
				throw new MissingTypeHintException(
					"Callback for hook '$hookName' has parameter '\${$parameter->getName()}' without a type hint",
				);
			}
		}
	}

	/**
	 * @param callable $callback
	 * @return ReflectionFunction|ReflectionMethod
	 * @throws ReflectionException
	 */
	private function getCallableReflection(callable $callback): ReflectionFunction|ReflectionMethod {
		if(is_string($callback)) {
			if(str_contains($callback, "::")) {
				return new ReflectionMethod($callback);
			}
			return new ReflectionFunction($callback);
		}

		if(is_array($callback)) {
			return new ReflectionMethod($callback[0], $callback[1]);
		}

		// Closure or invokable object
		return new ReflectionMethod($callback, "__invoke");
	}
}

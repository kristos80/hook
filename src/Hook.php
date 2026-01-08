<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use InvalidArgumentException;

/**
 * Class Hook
 *
 * @package Kristos80\Hook
 * @author Christos Athanasiadis <chris.k.athanasiadis@gmail.com>
 * @date 3/10/25
 */
final class Hook {

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
	private const ACCEPTED_ARGS = "acceptedArgs";

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
	 * @param int $acceptedArgs
	 * @return void
	 */
	public function addAction(array|string $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void {
		$this->addFilter($hookNames, $callback, $priority, $acceptedArgs);
	}

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs
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
				self::ACCEPTED_ARGS => $acceptedArgs,
			];
			$this->filters[$hookName][self::SORTED] = FALSE;
		}
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws CircularDependencyException
	 */
	public function doAction(string $hookName, ...$arg): void {
		$this->applyFilter($hookName, ...$arg);
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return mixed
	 * @throws InvalidArgumentException
	 * @throws CircularDependencyException
	 */
	public function applyFilter(string $hookName, ...$arg): mixed {
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

		$argCounter = count($arg);
		$runOnce = FALSE;
		$result = NULL;

		foreach($this->filters[$hookName][self::CALLBACKS] as $priority) {
			foreach($priority as $hook) {
				if($argCounter < $hook[self::ACCEPTED_ARGS]) {
					throw new InvalidArgumentException("Action '$hookName' should have '$argCounter' arguments or less. '{$hook["acceptedArgs"]}' provided");
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
}

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
	 * @var array
	 */
	private array $filters = [];

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
			$this->filters[$hookName]["callbacks"][$priority] = $this->filters[$hookName]["callbacks"][$priority] ?? [];
			$this->filters[$hookName]["callbacks"][$priority][] = [
				"callback" => $callback,
				"acceptedArgs" => $acceptedArgs,
			];
			$this->filters[$hookName]["sorted"] = FALSE;
		}
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return void
	 */
	public function doAction(string $hookName, ...$arg): void {
		$this->applyFilter($hookName, ...$arg);
	}

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return mixed
	 */
	public function applyFilter(string $hookName, ...$arg): mixed {
		if(!($this->filters[$hookName] ?? NULL)) {
			return NULL;
		}

		if(!$this->filters[$hookName]["sorted"]) {
			ksort($this->filters[$hookName]["callbacks"]);
			$this->filters[$hookName]["sorted"] = TRUE;
		}

		$argCounter = count($arg);
		$runOnce = FALSE;
		$result = NULL;

		foreach($this->filters[$hookName]["callbacks"] as $priority) {
			foreach($priority as $hook) {
				if($argCounter < $hook["acceptedArgs"]) {
					throw new InvalidArgumentException("Action '$hookName' should have '$argCounter' arguments or less. '{$hook["acceptedArgs"]}' provided");
				}

				if(!$runOnce) {
					$result = array_shift($arg);
					$runOnce = TRUE;
				}

				/**
				 * @var mixed $result
				 */
				$result = ($hook["callback"])($result, ...$arg);
			}
		}

		return $result;
	}
}

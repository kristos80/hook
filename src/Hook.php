<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use InvalidArgumentException;

/**
 * Class Hook
 *
 * @package Kristos80\Hooks
 * @author Christos Athanasiadis <chris.k.athanasiadis@gmail.com>
 * @date 3/10/25
 */
final class Hook {

	/**
	 * @var array
	 */
	private array $filters = [];

	/**
	 * @param string|array $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs
	 * @return void
	 */
	public function addAction($hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void {
		$this->addFilter($hookNames, $callback, $priority, $acceptedArgs);
	}

	/**
	 * @param string|array $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs
	 * @return void
	 */
	public function addFilter($hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void {
		if(is_string($hookNames)) {
			$hookNames = [$hookNames];
		}

		if(!is_array($hookNames)) {
			throw new InvalidArgumentException("'\$hookNames' should be a string or an array");
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
	public function applyFilter(string $hookName, ...$arg) {
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
				$callback = $hook["callback"];
				$acceptedArgs = $hook["acceptedArgs"];
				if($argCounter < $acceptedArgs) {
					throw new InvalidArgumentException("Action '$hookName' should have '$argCounter' arguments or less. '$acceptedArgs' provided");
				}

				if(!$runOnce) {
					$result = array_shift($arg);
					$runOnce = TRUE;
				}

				$passedArgs = array_slice($arg, 0, $acceptedArgs - 1);

				/**
				 * @var mixed $result
				 */
				$result = $callback($result, ...$passedArgs);
			}
		}

		return $result;
	}
}
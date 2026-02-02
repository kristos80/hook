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
interface HookInterface {

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs
	 * @return void
	 */
	public function addAction(array|string $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void;

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param int $priority
	 * @param int $acceptedArgs
	 * @return void
	 */
	public function addFilter(array|string $hookNames, callable $callback, int $priority = 10, int $acceptedArgs = 0): void;

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return void
	 * @throws InvalidArgumentException
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function doAction(string $hookName, ...$arg): void;

	/**
	 * @param string $hookName
	 * @param ...$arg
	 * @return mixed
	 * @throws
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function applyFilter(string $hookName, ...$arg): mixed;
}

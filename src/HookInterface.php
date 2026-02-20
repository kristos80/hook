<?php
declare(strict_types=1);

namespace Kristos80\Hook;

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
	 * @param array|int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
	 */
	public function addAction(array|string $hookNames, callable $callback, array|int $priority = 10, int $acceptedArgs = 0): void;

	/**
	 * @param array|string $hookNames
	 * @param callable $callback
	 * @param array|int $priority
	 * @param int $acceptedArgs @deprecated No longer used - kept for backwards compatibility
	 * @return void
	 */
	public function addFilter(array|string $hookNames, callable $callback, array|int $priority = 10, int $acceptedArgs = 0): void;

	/**
	 * @param string $hookName
	 * @param ...$arg Pass `requireTypedParameters: true` as a named argument to enforce type hints on callbacks
	 * @return void
	 * @throws CircularDependencyException
	 * @throws MissingTypeHintException
	 */
	public function doAction(string $hookName, ...$arg): void;

	/**
	 * @param string $hookName
	 * @param ...$arg Pass `requireTypedParameters: true` as a named argument to enforce type hints on callbacks
	 * @return mixed
	 * @throws CircularDependencyException
	 * @throws MissingTypeHintException
	 */
	public function applyFilter(string $hookName, ...$arg): mixed;

	/**
	 * @param string $hookName
	 * @return int|null
	 */
	public function getMinPriority(string $hookName): ?int;

	/**
	 * @param string $hookName
	 * @return int|null
	 */
	public function getMaxPriority(string $hookName): ?int;
}

<?php
declare(strict_types=1);

namespace Kristos80\Hooks\Tests\Unit;

use Kristos80\Hook\Hook;
use Kristos80\Hook\Tests\TestCase;
use Kristos80\Hook\CircularDependencyException;
use Kristos80\Hook\InvalidNumberOfArgumentsException;

final class HookTest extends TestCase {

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_filter_executes_in_priority_order(): void {
		$hook = new Hook();

		// Add filter with default priority (10)
		$hook->addFilter("test_filter", function(int $value) {
			if($value === 0) {
				return $value;
			}
			return $value + 1;
		});

		// Add filter with higher priority (9) - should run first
		$hook->addFilter("test_filter", function(int $value) {
			if($value > 0) {
				return $value + 1;
			}
			return $value;
		}, 9);

		$resultZero = $hook->applyFilter("test_filter", 0);
		$this->assertEquals(0, $resultZero);

		$resultOne = $hook->applyFilter("test_filter", 1);
		$this->assertEquals(3, $resultOne);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_action_executes_callback(): void {
		$hook = new Hook();
		$executed = NULL;

		$hook->addAction("test_action", function() use (&$executed) {
			$executed = "test_action";
		});

		$hook->doAction("test_action");

		$this->assertEquals("test_action", $executed);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_empty_filter_returns_null_on_empty_arguments(): void {
		$hook = new Hook();
		$result = $hook->applyFilter("nonexistent_filter");

		$this->assertNull($result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_empty_filter_returns_first_argument(): void {
		$hook = new Hook();
		$result = $hook->applyFilter("nonexistent_filter", "1");

		$this->assertEquals("1", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 */
	public function test_extra_arguments_throws_exception(): void {
		$hook = new Hook();

		$hook->addFilter("test_with_params", function(int $value, string $mode) {
			return $value;
		}, 10, 2);

		$this->expectException(InvalidNumberOfArgumentsException::class);
		$hook->applyFilter("test_with_params", 5);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_multiple_callbacks_at_same_priority(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function(int $value) {
			return $value + 1;
		});

		$hook->addFilter("test_filter", function(int $value) {
			return $value * 2;
		});

		$result = $hook->applyFilter("test_filter", 5);
		// First callback: 5 + 1 = 6
		// Second callback: 6 * 2 = 12
		$this->assertEquals(12, $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_multiple_hook_names(): void {
		$hook = new Hook();
		$counter = 0;

		$hook->addAction([
			"hook_one",
			"hook_two",
			"hook_three",
		], function() use (&$counter) {
			$counter++;
		});

		$hook->doAction("hook_one");
		$hook->doAction("hook_two");
		$hook->doAction("hook_three");

		$this->assertEquals(3, $counter);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_sorted_flag_prevents_repeated_sorting(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function(int $value) {
			return $value + 1;
		});

		$hook->addFilter("test_filter", function(int $value) {
			return $value + 1;
		}, 5);

		// First call should sort
		$result1 = $hook->applyFilter("test_filter", 0);
		$this->assertEquals(2, $result1);

		// Second call should NOT re-sort (reusing sorted callbacks)
		$result2 = $hook->applyFilter("test_filter", 0);
		$this->assertEquals(2, $result2);

		// Adding new callback(s) should mark as unsorted
		$hook->addFilter("test_filter", function(int $value) {
			return $value + 10;
		}, 9);

		$hook->addFilter("test_filter", function(int $value) {
			return $value * 5;
		}, 6);

		// Should re-sort and include new callback
		$result3 = $hook->applyFilter("test_filter", 0);
		$this->assertEquals(16, $result3);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_first_argument_is_separated_from_other_arguments(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function(int $value, int $constant) {
			return $value * $constant;
		}, 10, 2);

		$hook->addFilter("test_filter", function(int $value, int $constant) {
			return $value + $constant;
		}, 1, 2);

		$result = $hook->applyFilter("test_filter", 1, 5);
		$this->assertEquals(30, $result);
	}

	/**
	 * @return void
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_circular_dependency_exception_is_triggered(): void {
		$hook = new Hook();

		$hook->addAction("test", function() use ($hook) {
			$hook->doAction("test");
		});

		$exception = NULL;
		try {
			$hook->doAction("test");
		} catch(CircularDependencyException $exception) {
		}

		$this->assertInstanceOf(CircularDependencyException::class, $exception);
	}

	/**
	 * @return void
	 * @throws InvalidNumberOfArgumentsException
	 */
	public function test_circular_dependency_detected_after_nested_hook_completes(): void {
		$hook = new Hook();
		$callCount = 0;

		$hook->addAction("outer", function() use ($hook, &$callCount) {
			$callCount++;
			// First call nested hook
			$hook->doAction("inner");
			// After inner completes, try to call outer again (should be circular)
			if($callCount === 1) {
				$hook->doAction("outer");
			}
		});

		$hook->addAction("inner", function() {
			// Just completes normally
		});

		$exception = NULL;
		try {
			$hook->doAction("outer");
		} catch(CircularDependencyException $exception) {
		}

		$this->assertInstanceOf(CircularDependencyException::class, $exception);
		// Verify it's the 'outer' hook that's detected as circular, not 'inner'
		$this->assertStringContainsString("'outer'", $exception->getMessage());
	}
}

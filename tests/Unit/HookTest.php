<?php
declare(strict_types=1);

namespace Kristos80\Hooks\Tests\Unit;

use Kristos80\Hook\Hook;
use Kristos80\Hook\Tests\TestCase;
use Kristos80\Hook\MissingTypeHintException;
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
	 * @throws MissingTypeHintException
	 */
	public function test_empty_filter_returns_first_argument(): void {
		$hook = new Hook();
		$result = $hook->applyFilter("nonexistent_filter", "1");

		$this->assertEquals("1", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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
	 * @throws MissingTypeHintException
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

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_throws_exception_for_untyped_callback(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function($value) {
			return $value;
		});

		$this->expectException(MissingTypeHintException::class);
		$this->expectExceptionMessage("parameter '\$value' without a type hint");
		$hook->applyFilter("test_filter", "test", requireTypedParameters: TRUE);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_passes_for_typed_callback(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function(string $value): string {
			return strtoupper($value);
		});

		$result = $hook->applyFilter("test_filter", "test", requireTypedParameters: TRUE);
		$this->assertEquals("TEST", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_is_not_passed_to_callback(): void {
		$hook = new Hook();
		$receivedArgs = [];

		$hook->addFilter("test_filter", function(string $value, string $extra) use (&$receivedArgs): string {
			$receivedArgs = func_get_args();
			return $value . $extra;
		}, 10, 2);

		$result = $hook->applyFilter("test_filter", "hello", "world", requireTypedParameters: TRUE);

		$this->assertEquals("helloworld", $result);
		$this->assertCount(2, $receivedArgs);
		$this->assertEquals([
			"hello",
			"world",
		], $receivedArgs);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_works_with_do_action(): void {
		$hook = new Hook();

		$hook->addAction("test_action", function($value) {
			// Untyped parameter
		});

		$this->expectException(MissingTypeHintException::class);
		$hook->doAction("test_action", "test", requireTypedParameters: TRUE);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_callbacks_work_normally_without_require_typed_parameters(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", function($value) {
			return strtoupper($value);
		});

		// Should work fine without requireTypedParameters
		$result = $hook->applyFilter("test_filter", "test");
		$this->assertEquals("TEST", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_with_static_method_callback(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", TypedCallbackHelper::class . "::transform");

		$result = $hook->applyFilter("test_filter", "test", requireTypedParameters: TRUE);
		$this->assertEquals("TEST", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_with_array_callback(): void {
		$hook = new Hook();
		$helper = new TypedCallbackHelper();

		$hook->addFilter("test_filter", [$helper, "instanceTransform"]);

		$result = $hook->applyFilter("test_filter", "test", requireTypedParameters: TRUE);
		$this->assertEquals("test_instance", $result);
	}

	/**
	 * @return void
	 * @throws CircularDependencyException
	 * @throws InvalidNumberOfArgumentsException
	 * @throws MissingTypeHintException
	 */
	public function test_require_typed_parameters_with_function_string_callback(): void {
		$hook = new Hook();

		$hook->addFilter("test_filter", "strtoupper");

		$result = $hook->applyFilter("test_filter", "test", requireTypedParameters: TRUE);
		$this->assertEquals("TEST", $result);
	}
}

class TypedCallbackHelper {

	public static function transform(string $value): string {
		return strtoupper($value);
	}

	public function instanceTransform(string $value): string {
		return $value . "_instance";
	}
}

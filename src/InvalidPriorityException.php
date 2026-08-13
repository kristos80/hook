<?php
declare(strict_types=1);

namespace Kristos80\Hook;

/**
 * Thrown by {@see Hook::addFilter()} / {@see Hook::addAction()} when the resolved priority
 * for a hook is not an integer (e.g. a non-int slipped through the array<int, int>|int union).
 */
final class InvalidPriorityException extends HookException {

}

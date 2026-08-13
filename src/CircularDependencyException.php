<?php
declare(strict_types=1);

namespace Kristos80\Hook;

/**
 * Thrown when a hook is dispatched while it is already on the running stack, which would
 * otherwise cause infinite recursion (e.g. a callback for hook "X" triggers hook "X" again).
 */
final class CircularDependencyException extends HookException {

}
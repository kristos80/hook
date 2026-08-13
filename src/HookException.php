<?php
declare(strict_types=1);

namespace Kristos80\Hook;

use Exception;

/**
 * Base class for every exception thrown by the Hook system. Callers that want to catch any
 * hook-related failure without discriminating between causes can catch this type.
 */
abstract class HookException extends Exception {

}
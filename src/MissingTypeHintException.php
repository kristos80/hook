<?php
declare(strict_types=1);

namespace Kristos80\Hook;

/**
 * Thrown during {@see Hook::applyFilter()} / {@see Hook::doAction()} when strict typing was
 * requested (via the `requireTypedParameters: true` named argument) and a registered callback
 * has at least one parameter without a declared type hint.
 */
final class MissingTypeHintException extends HookException {

}

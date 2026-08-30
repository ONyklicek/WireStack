<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A transition the state machine does not allow.
 *
 * Loud rather than a silent no-op: the alternative is a record that quietly
 * stayed where it was while the user believes it moved on, and a process built
 * on that is worse than one that stops. A guard *veto* is a different thing —
 * that is a domain decision, reported rather than thrown.
 */
final class IllegalTransitionException extends RuntimeException implements WireException
{
    public static function notAllowed(string $from, string $to, string $status): self
    {
        return new self(
            "[{$status}] does not allow [{$from}] → [{$to}]. Declare it with ".
            '->allow() if it is legal, or offer only the transitions '.
            'availableFrom() returns — an action a user cannot complete should '.
            'not have been rendered.'
        );
    }

    public static function notAStatus(string $given, string $status): self
    {
        return new self(
            "[{$given}] is not a case of [{$status}]. A workflow is defined over ".
            'one status enum, and a transition to something outside it is a '.
            'different machine.'
        );
    }

    public static function missingColumn(string $status): self
    {
        return new self(
            "The workflow for [{$status}] does not say which column holds the ".
            'status. Name it with ->column(\'status\'): the machine has to read '.
            'the current state off the record before it can decide anything.'
        );
    }
}

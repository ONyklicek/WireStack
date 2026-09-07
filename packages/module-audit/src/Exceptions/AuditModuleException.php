<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * A configuration this module was given and cannot work from.
 *
 * `InvalidArgumentException` per ADR 0022: the value came from
 * `config/wire-module-audit.php`, so it is a bad argument rather than a bad
 * state — nothing about the running application would make it right.
 *
 * Thrown rather than shrugged off with an empty screen. A resource pointed at
 * the wrong class renders as a log with nothing in it, and "nothing happened
 * here" is the one thing an audit screen must never say by mistake.
 */
final class AuditModuleException extends InvalidArgumentException implements WireException
{
    public static function modelIsNotAnAuditEntry(string $class): self
    {
        $base = AuditEntry::class;

        return new self(
            "[{$class}] is configured as `wire-module-audit.model`, but it does not extend [{$base}]. ".
            'These screens read the trail through that model — its casts, its `user()` relation and its '.
            'change diff — so a class that only happens to have the same columns would render an empty '.
            'log rather than a broken one. Extend it, or leave the setting at core\'s own model.'
        );
    }
}

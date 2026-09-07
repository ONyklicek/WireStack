<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Support;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Foundation\ValueObjects\ChangeSet;

/**
 * What actually changed, in the shape a screen can render.
 *
 * Two owners above it and nothing of its own. The diff is core's —
 * {@see AuditEntry::getChangeDiff()} pairs the old and new values and drops the
 * keys that did not move — and how a value *reads* is core's too, in
 * {@see ChangeSet}.
 *
 * The display rule used to live here, and that was the drift: core's trail
 * slide-over drew the same diff from a ternary in a Blade file, where a boolean
 * `true` came out as `1` and no test could reach it. Two answers to one question
 * in two packages, and the one nobody could see was the wrong one.
 *
 * What is left is this module's own question — the entry may be any model, and
 * only an `AuditEntry` has a diff to ask for.
 */
final class Changes
{
    /**
     * One row per changed field: what it was, what it became.
     *
     * @return array<int, array{field: string, before: string|null, after: string|null}>
     */
    public static function rows(Model $entry): array
    {
        return self::of($entry)->rows;
    }

    /**
     * The whole change set, for a caller that wants to ask more than one thing
     * of it.
     */
    public static function of(Model $entry): ChangeSet
    {
        return $entry instanceof AuditEntry
            ? ChangeSet::fromDiff($entry->getChangeDiff())
            : ChangeSet::empty();
    }

    /**
     * Just the names, for the list — the column that answers "what did they
     * touch?" without opening the entry.
     *
     * @return array<int, string>
     */
    public static function fields(Model $entry): array
    {
        return self::of($entry)->fields();
    }
}

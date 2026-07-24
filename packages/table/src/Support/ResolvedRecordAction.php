<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Actions\RecordActionResolver;

/**
 * A record-action binding after normalization: whatever the owner passed to
 * `recordActions()` — a name, a plain {@see Action}, or a {@see RecordAction} —
 * reduced to the shape the runtime and the view consume, with the default
 * trigger already applied.
 *
 * Produced by {@see RecordActionResolver}; carries no
 * behaviour of its own.
 */
final class ResolvedRecordAction
{
    /**
     * @param  string  $name  Action name (the execution key the endpoints resolve).
     * @param  array<int, string>  $triggerTypes  Resolved trigger types (default applied).
     * @param  Action|null  $action  The wrapped action, or null for a name reference.
     * @param  bool  $rendersInRowActions  Whether it also shows as a toolbar button.
     * @param  array<int, string>  $keyShortcuts  Keys bound via `onKey()`, carried so a
     *                                            name-referenced binding keeps its shortcut
     *                                            even with no action to stamp it on.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $triggerTypes,
        public readonly ?Action $action,
        public readonly bool $rendersInRowActions,
        public readonly array $keyShortcuts = [],
    ) {}

    public function hasTrigger(string $type): bool
    {
        return in_array($type, $this->triggerTypes, true);
    }
}

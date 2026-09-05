<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'table.composing' hook.
 *
 * Dispatched once, when a host has built its table and before anything reads it
 * — the counterpart of `form.configuring`, and the hook that was missing when
 * ADR 0029 promised an application could add a column to a module's list.
 *
 * `table.configuring` could not keep that promise, and measuring it is what
 * produced this class: that hook runs inside the query service, on arrays the
 * planner is about to consume, so a column added there is searched and sorted on
 * and **never rendered**. It steers a query; this composes a table.
 *
 * Whatever the callbacks leave in `$columns` and `$filters` is applied back to
 * the table instance, so a callback appends rather than replacing:
 *
 * ```php
 * $payload->columns = [...$payload->columns, TextColumn::make('internal_note')];
 * ```
 *
 * Typed only — see the note on {@see FormConfiguringPayload}.
 */
final class TableComposingPayload implements HasHookTarget
{
    /**
     * @param  object  $table  The composed table instance
     * @param  array<int, object>  $columns  Column definitions (modifiable)
     * @param  array<int, object>  $filters  Filter definitions (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $table,
        public array $columns,
        public array $filters,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'columns' => $this->columns,
            'filters' => $this->filters,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}

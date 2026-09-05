<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'table.configuring' hook.
 *
 * Dispatched before the table configuration is finalized.
 * Plugins can modify columns and filters before the query is planned.
 */
final class TableConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $table  The table configuration object
     * @param  array<int, object>  $columns  Column definitions
     * @param  array<int, object>  $filters  Filter definitions
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

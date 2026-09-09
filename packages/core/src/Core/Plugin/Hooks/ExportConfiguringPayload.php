<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'export.configuring' hook.
 *
 * Dispatched where a table resolves what it would export — its config, its
 * filtered query and its visible columns — and that is one place for both
 * deliveries: a streamed download and a queued file are the same call. An export
 * that changed depending on how it was delivered is therefore not expressible,
 * which is the property the dispatch site was chosen for.
 *
 * The query is the table's own, already filtered, sorted and searched. Constrain
 * it in place or replace it:
 *
 * ```php
 * $payload->query->whereNotNull('approved_at');
 * $payload->columns = array_filter($payload->columns, fn ($c) => $c->getName() !== 'cost');
 * ```
 *
 * Column visibility has already been applied, so what arrives here is what the
 * file would contain — not everything the table declares.
 *
 * Typed only.
 */
final class ExportConfiguringPayload implements HasHookTarget
{
    /**
     * @param  object  $export  The export config the action declared
     * @param  object  $query  The table's filtered query (modifiable)
     * @param  array<int, mixed>  $columns  The columns that would be written (modifiable)
     * @param  HookTarget|null  $target  Which component this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $export,
        public object $query,
        public array $columns,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'export' => $this->export,
            'query' => $this->query,
            'columns' => $this->columns,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}

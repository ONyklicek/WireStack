<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'search.querying' hook.
 *
 * Dispatched once **per resource**, on the query the palette is about to run,
 * rather than once per term. Per resource because that is what makes the hook
 * addressable: `for: 'invoices'` narrows a callback to one module's rows, which
 * a term-level payload could not express — it would hand every callback the
 * whole palette and leave the `if` to be written by hand, once per installed
 * module.
 *
 * The query is already limited and, since V2.4, already tenant-scoped by the
 * global scope. Per-record authorization runs **after** this, so a callback
 * cannot widen its way past a policy:
 *
 * ```php
 * $payload->query->whereNull('archived_at');
 * ```
 *
 * Typed only.
 */
final class SearchQueryingPayload implements HasHookTarget
{
    /**
     * @param  object  $query  The builder about to run (modifiable)
     * @param  string  $term  The trimmed search term
     * @param  string  $resource  The resource class being searched
     * @param  HookTarget|null  $target  Which resource this came from, for scoped callbacks
     */
    public function __construct(
        public object $query,
        public readonly string $term,
        public readonly string $resource,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'term' => $this->term,
            'resource' => $this->resource,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

/**
 * What the table's chrome asks of its host: whether the deferred table has
 * loaded, how it polls and listens, how its sub-rows and groups open, and the
 * saved views it can offer.
 *
 * The render layer's half of the same bargain as {@see ShowsTableColumns}: the
 * shell plan (`Support\ShellRenderPlan`) names what it reads instead of calling
 * into a `mixed` component, so a slice can be exercised against a double, and a
 * host method renamed in `WithTable` fails a test rather than a page.
 * `WithTable` answers all of it; a component needs no `implements`.
 */
interface HostsTableShell
{
    public function isTableReady(): bool;

    /** @return array<string, mixed> `['enabled' => false]`, or the interval plus whether it is running. */
    public function getTablePollingConfig(): array;

    public function getTablePollingAttribute(): ?string;

    public function getTableLiveChannel(): ?string;

    public function expandsSubRowsByDefault(): bool;

    public function tableHasGroupSummaries(): bool;

    /** @return array<int, string> */
    public function getTableViews(): array;

    /** @return array<int, string> */
    public function getSharedTableViews(): array;

    public function canShareTableViews(): bool;
}

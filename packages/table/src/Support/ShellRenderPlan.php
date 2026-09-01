<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;

/**
 * The frame around the rows: whether it renders yet, how it stays current, and
 * which of its optional regions exist.
 *
 * Part of {@see TableRenderPlan}, and the last group the head block gave up.
 * Three concerns that share one property — none of them is about a row:
 *
 *  - **arrival** — a `lazy()` table renders a placeholder until the host says it
 *    is ready, and the placeholder is the only thing in the document until then;
 *  - **freshness** — `poll()` produces the `wire:poll` attribute and the config
 *    the Stop control reads; `live(broadcast: true)` adds a channel to listen on.
 *    The channel is null without the opt-in, so a table that did not ask for it
 *    ships no listener and needs no channel authorization;
 *  - **regions** — filters, sub-rows, grouping and the view menu each earn their
 *    markup or do not. These are the conditions the view branches on, hoisted out
 *    of it.
 *
 * The three `&&` pairs are the reason this is worth a class rather than four
 * `$table->…()` calls in Blade. `isSubRowsExpandable`, `allRowsExpanded` and
 * `hasGroupSummaries` are each only meaningful when their feature is on, and the
 * guard is not decoration: a table with no sub-rows still answers
 * {@see Table::isSubRowsExpandable()} from a default, and rendering an expand-all
 * control for rows that cannot expand is exactly the bug the guard prevents.
 *
 * `$hasViewMenu` is the one value with an edge outside this group: the menu
 * holds column toggles, sub-row expansion, saved views, or any mix, so the
 * columns group has to have been resolved first. It is passed in rather than reached for — the same
 * shape {@see PagingRenderPlan} uses — which keeps the group's inputs visible in
 * its signature instead of hidden in its body.
 */
final class ShellRenderPlan
{
    /**
     * @param  ?string  $lazyPlaceholder  Custom placeholder markup, or null for the default.
     * @param  array<string, mixed>  $pollingConfig  `['enabled' => false]`, or the interval plus whether it is running.
     * @param  ?string  $pollingAttribute  The literal `wire:poll…` value, or null when not polling.
     * @param  ?string  $liveChannel  The broadcast channel to listen on, or null without `live(broadcast: true)`.
     * @param  array<int, Filter>  $filters  The table-level filters (column filters live on the columns group).
     * @param  array<int, string>  $savedViews  Names of this user's saved views, empty when the feature is off.
     * @param  string  $viewMenuLabel  Narrows to "toggle columns" when that is all the menu holds.
     */
    private function __construct(
        public readonly bool $isLazy,
        public readonly bool $isTableReady,
        public readonly ?string $lazyPlaceholder,
        public readonly array $pollingConfig,
        public readonly ?string $pollingAttribute,
        public readonly ?string $liveChannel,
        public readonly array $filters,
        public readonly bool $hasFilters,
        public readonly bool $hasSubRows,
        public readonly bool $isSubRowsExpandable,
        public readonly bool $allRowsExpanded,
        public readonly bool $hasGrouping,
        public readonly bool $hasGroupSummaries,
        public readonly bool $hasSavedViews,
        /** @var array<int, string> */
        public readonly array $savedViews,
        public readonly bool $hasViewMenu,
        public readonly string $viewMenuLabel,
    ) {}

    public static function resolve(Table $table, mixed $component, bool $hasColumnToggles): self
    {
        $filters = $table->getFilters();
        $hasSubRows = $table->hasSubRows();
        $hasGrouping = $table->hasGrouping();
        $isSubRowsExpandable = $hasSubRows && $table->isSubRowsExpandable();
        $hasSavedViews = $table->getSavedViewsKey() !== null;

        return new self(
            isLazy: $table->isLazy(),
            isTableReady: $component->isTableReady(),
            lazyPlaceholder: $table->getLazyPlaceholder(),
            pollingConfig: $component->getTablePollingConfig(),
            pollingAttribute: $component->getTablePollingAttribute(),
            liveChannel: $component->getTableLiveChannel(),
            filters: $filters,
            hasFilters: $filters !== [],
            hasSubRows: $hasSubRows,
            isSubRowsExpandable: $isSubRowsExpandable,
            allRowsExpanded: $hasSubRows && $component->expandsSubRowsByDefault(),
            hasGrouping: $hasGrouping,
            hasGroupSummaries: $hasGrouping && $component->tableHasGroupSummaries(),
            hasSavedViews: $hasSavedViews,
            savedViews: $hasSavedViews ? $component->getTableViews() : [],
            hasViewMenu: $hasColumnToggles || $isSubRowsExpandable || $hasSavedViews,
            viewMenuLabel: $hasColumnToggles && ! $isSubRowsExpandable && ! $hasSavedViews
                ? __('wire-table::messages.toggle_columns')
                : __('wire-table::messages.view_options'),
        );
    }
}

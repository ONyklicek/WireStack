<?php

declare(strict_types=1);

namespace NyonCode\WireCore\GlobalSearch;

use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Workspace;

/**
 * The application's own menu, as palette rows.
 *
 * The cheapest of the palette's sources, because it builds nothing: {@see
 * Workspace::items()} already returns the menu flat and fully resolved — the
 * label filled in from `pluralLabel()` where an entry named none, the icon, and
 * the URL resolved through `ResolvesPageUrls` for this zone. `Core/Resources` is
 * L1 and this module is L2, so reading it is a permitted edge rather than a
 * favour.
 *
 * ## Two rules it must not get wrong
 *
 * **Authorization here is not the authorization records get.** A record row is
 * filtered by a policy over an instance ({@see GlobalSearch::canView()}); a menu
 * entry is filtered by `isVisible()`, which is what the author wrote plus
 * `authorize()`/`permission()` through `HasAuthorization`. `Workspace` has
 * already applied it, along with hidden groups. Re-running the record check here
 * would be asking a policy about a thing that is not a record.
 *
 * **`linkedOnly`, always.** A menu draws an entry with no URL perfectly well —
 * it is a heading with nothing behind it. A palette cannot: every row in it is
 * something Enter does, and a row that does nothing reads as broken. So an entry
 * this zone cannot reach is dropped rather than listed dead.
 */
class PaletteNavigation
{
    /** The reserved group key these rows are collected under. */
    public const GROUP = 'palette:navigation';

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * Menu entries whose label matches the term.
     *
     * Matched on the label alone, and case-insensitively: the label is the only
     * thing a user sees in a menu, so it is the only thing they can be typing
     * at. The registry key is deliberately not searched — it is an identifier,
     * and matching `gs-orders` for "gs" would surface rows for a string the user
     * has never been shown.
     *
     * No cap. The menu is small by construction and already ordered by `sort()`;
     * a limit here would drop the entry an application put last on purpose.
     *
     * @return array<int, GlobalSearchResult>
     */
    public function search(string $term, ?string $zone = null): array
    {
        $term = trim($term);

        if ($term === '') {
            return [];
        }

        $rows = [];

        foreach ($this->workspace->items($zone, linkedOnly: true) as $key => $item) {
            $label = $item->getLabel();

            if ($label === null || ! $this->matches($label, $term)) {
                continue;
            }

            $rows[] = $this->row($key, $label, $item);
        }

        return $rows;
    }

    /**
     * Case-insensitive substring, the same shape the record search asks the
     * database for — so a term that finds "Invoices" in the menu is the term
     * that would have found it in a column.
     */
    protected function matches(string $label, string $term): bool
    {
        return mb_stripos($label, $term) !== false;
    }

    /**
     * A menu entry as a row.
     *
     * `recordKey` is null and the registry key goes in `resourceKey`, which is
     * what `wire:key` is built from — a navigation row is about no record, and
     * inventing one would make the two kinds look alike to everything downstream.
     */
    protected function row(string $key, string $label, NavigationItem $item): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: $key,
            recordKey: null,
            title: $label,
            subtitle: null,
            url: $item->getUrl(),
            icon: $item->getIcon(),
            kind: PaletteRowKind::Navigation,
        );
    }
}

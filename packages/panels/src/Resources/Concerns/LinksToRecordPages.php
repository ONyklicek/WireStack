<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Resources\Navigation\RecordPages;

/**
 * The other pages of the record this page is showing.
 *
 * What a reader standing on an edit screen has no other way to reach: today the
 * only way from `Edit` to `View` is back through the list. The menu cannot
 * supply it — a menu knows resources, not records — and breadcrumbs cannot
 * either, because they lead *up* and this leads *across*.
 *
 * Composed by the pages that have a record ({@see ResolvesOneRecord}) and a
 * resource ({@see BelongsToResource}); both are what it reads. A custom page of
 * a resource — an audit trail, a preview — composes the same three and gets its
 * own tab beside the others by declaring a `{record}` in its URI.
 *
 * Deliberately thin, and named for what it does rather than for what it has:
 * which pages are about one record, what they are called and in what order is
 * {@see RecordPages}; where they are and who may open them is
 * `reachablePageUrl()`, which the page already had. What is left here is the
 * shape a view draws — the same division `BelongsToResource` and
 * `ResolvesOneRecord` beside it follow.
 */
trait LinksToRecordPages
{
    /**
     * The kind of page this is — `view`, `edit`, or one the resource named.
     *
     * Public for the reason `$breadcrumbZone` beside it is: it is read from the
     * route while the **page** renders, and during a Livewire update the route
     * name is `livewire.update` and the answer is null (ADR 0027). Re-derived
     * per render, the current tab would be right on the first paint and unmarked
     * on every one after — and an edit page that re-renders on every keystroke
     * would lose it immediately.
     */
    public ?string $currentPage = null;

    /** Livewire calls this for the trait, on mount, after the page's own. */
    public function mountLinksToRecordPages(): void
    {
        $this->currentPage = Zone::currentPage();
    }

    /**
     * The record's pages, as rows a view can draw, keyed by page kind.
     *
     * `NavigationItem` rather than a shape of its own, for the reason the crumb
     * trail already uses it: "a label, an icon and usually a URL" has one owner
     * in this framework, and a tab is that and nothing more.
     *
     * **Fewer than two is none.** One tab is the page's own name written twice —
     * the same rule breadcrumbs follow for a trail of one. And a page whose URL
     * cannot be built is dropped rather than drawn dead: unlike a menu, where an
     * unlinked row honestly says "registered, not routed here", a tab that goes
     * nowhere is just a broken tab. That is also what quietly empties the bar for
     * a non-Eloquent record — there is no key to put in the URL, and the honest
     * answer to "where is the edit page of this" is that there is not one.
     *
     * @param  mixed  $record  The resolved record, when the caller already has it — a page
     *                         renders three or four of these and each would otherwise be a query.
     * @return array<string, NavigationItem>
     */
    public function subNavigation(mixed $record = null): array
    {
        $pages = RecordPages::of(static::$resource);

        if (count($pages) < 2) {
            return [];
        }

        $record ??= $this->nativeRecord();

        $items = [];

        foreach ($pages as $kind => $page) {
            $url = $this->reachablePageUrl($kind, $record instanceof Model ? $record : null);

            if ($url === null) {
                continue;
            }

            $items[$kind] = NavigationItem::make($page->getLabel())
                ->icon($page->getIcon())
                ->url($url)
                ->sort($page->getSort());
        }

        return count($items) > 1 ? $items : [];
    }
}

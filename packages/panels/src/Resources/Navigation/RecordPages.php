<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Navigation;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WirePanels\Routing\ResourceRoutes;

/**
 * A resource's pages that are about **one record**, named and in order.
 *
 * The list a record's sub-navigation draws. Nothing new is declared for it:
 * `pages()` is already the full list, and {@see ResourceRoutes::takesRecord()}
 * already knows which of them take a record, because that is the same question
 * the router answers when it builds `{record}` into the URL. A second
 * declaration — `subNavigation()` on the resource, listing the same classes a
 * second time — is the copy that stops matching `pages()` the first time
 * someone adds a page.
 *
 *   'index'  => ListInvoices::class,       // no record → not a tab
 *   'create' => CreateInvoice::class,      // no record → not a tab
 *   'view'   => ViewInvoice::class,        // tab
 *   'edit'   => EditInvoice::class,        // tab
 *   'history'=> RoutePage::make(History::class)->uri('{record}/history'),   // tab
 *
 * Keyed by the page kind, like `Workspace` keys its entries by resource key and
 * for the same reason: a tab is only half of what a row needs, and the other
 * half is which page it stands for. That is what lets the view mark the current
 * tab by comparing kinds — `Zone::currentPage()` — instead of comparing URL
 * strings, which is a question about trailing slashes standing in for one the
 * route name answers exactly.
 *
 * **No URLs and no authorization here.** Both need the zone the page was opened
 * in, which only the page knows (ADR 0027) — and it already owns
 * `reachablePageUrl()`, which answers them together.
 */
final class RecordPages
{
    /**
     * The record pages of a resource, named, in `sort()` order.
     *
     * @param  class-string|null  $resource
     * @return array<string, RoutePage> Keyed by page kind.
     */
    public static function of(?string $resource): array
    {
        if ($resource === null || ! is_a($resource, ProvidesPages::class, true)) {
            return [];
        }

        $pages = [];

        foreach ($resource::pages() as $kind => $declared) {
            $kind = (string) $kind;

            if (! ResourceRoutes::takesRecord($kind, $declared)) {
                continue;
            }

            // Cloned, not filled in place: `pages()` is free to hand back the
            // same objects on a second call, and a naming that mutated the
            // declaration would make the second answer differ from the first —
            // the bug `NavigationGroup::withItems()` copies to avoid.
            $page = $declared instanceof RoutePage ? clone $declared : RoutePage::make($declared);

            if (! $page->hasVisibleLabel()) {
                $page->label(self::label($kind));
            }

            $pages[$kind] = $page;
        }

        // Stable, and `uasort` rather than `usort`: the kind is the key, and a
        // list that has been ordered but can no longer say which page a tab is
        // cannot mark the current one.
        uasort($pages, static fn (RoutePage $a, RoutePage $b): int => $a->getSort() <=> $b->getSort());

        return $pages;
    }

    /**
     * What a page that named itself nothing is called.
     *
     * The four kinds the router knows are words this framework already ships in
     * both locales; anything else is the application's own key, humanised. A
     * page that wants neither says so with `RoutePage::label()`, which is why
     * this only runs when the declaration left the label empty.
     */
    private static function label(string $kind): string
    {
        $key = 'wire-panels::messages.page_kind.'.$kind;

        return Lang::has($key) ? (string) __($key) : Str::headline($kind);
    }
}

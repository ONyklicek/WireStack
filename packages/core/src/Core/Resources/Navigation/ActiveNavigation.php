<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * Where the reader is, as a menu has to ask it.
 *
 * One owner for a question that was being answered three times in one Blade
 * file: a registered entry matched the current **key**, a hand-written `->url()`
 * entry matched the current **URL** exactly, and a parent matched by scanning
 * its children with a third copy of the second rule. Three rules for one
 * question, in a template, with a fourth surface (a record's sub-navigation, a
 * second menu shape) about to need the same answer — which is how a menu and
 * the thing beside it start disagreeing about what is highlighted.
 *
 * Built **once per page render** and handed down. Not re-derived per row: it
 * reads {@see Zone}, and a zone read inside a Livewire update answers
 * `livewire.update` and therefore null (ADR 0027). A sidebar re-renders on every
 * navigation; a row asking for itself would be right on the first paint only.
 *
 * ## What makes an entry active
 *
 * In this order, first answer wins:
 *
 *  1. **What the entry declared.** {@see NavigationItem::activeWhen()} — a
 *     closure, or path/route-name patterns. Nothing else is consulted.
 *  2. **Its registered key**, against the key of the route being rendered. This
 *     is what keeps the Invoices row lit on `wire.invoices.edit`: the menu knows
 *     the resource, not the page.
 *  3. **Its URL**, matched exactly.
 *
 * ### Why rule 3 is exact and not "is an ancestor of where I am"
 *
 * Because it cannot be made safe from here. A prefix rule would light
 * `/settings` on `/settings/general/edit`, which is what anyone would want —
 * and it would equally light a `Home` entry pointing at the shell's own mount
 * path on *every* page under it, for ever, because this class has no way to
 * know that `/admin` is a root and `/settings` is a section. A row that is
 * always highlighted is a worse and louder defect than a row that is not
 * highlighted when it could be.
 *
 * So the entry that knows says so, in one line, and gets exactly what it meant:
 *
 *   NavigationItem::make('Settings')
 *       ->url(route('settings.general'))
 *       ->activeWhen('settings/*');
 */
final readonly class ActiveNavigation
{
    /**
     * @param  string|null  $key  The registered key of the route being rendered.
     * @param  string|null  $page  Its page kind — `index`, `view`, `edit`, or the resource's own.
     * @param  string|null  $url  The current URL, without its query string.
     * @param  string|null  $path  The current path, without a leading slash — what a pattern is matched against.
     * @param  string|null  $routeName  The current route name — the other thing a pattern may mean.
     */
    public function __construct(
        public ?string $key = null,
        public ?string $page = null,
        public ?string $url = null,
        public ?string $path = null,
        public ?string $routeName = null,
    ) {}

    /**
     * Read it off the request being rendered.
     *
     * Everything it needs arrives from one place each: the key and the page kind
     * from {@see Zone}, which owns the shape of a page route's name, and the URL
     * from the request. Nothing here re-derives what that class already parses.
     */
    public static function current(): self
    {
        return new self(
            key: Zone::currentKey(),
            page: Zone::currentPage(),
            url: url()->current(),
            path: request()->path(),
            routeName: Route::currentRouteName(),
        );
    }

    /**
     * The same reading, with the key a host resolved for itself.
     *
     * `<x-wire-admin::sidebar :active-key="…">` exists so a shell that already
     * knows which entry is current can say so — a menu drawn beside a page
     * rather than by it. Overriding the key rather than rebuilding keeps the URL
     * and the route name the request's, which is what the other two rules need.
     */
    public function withKey(?string $key): self
    {
        return new self($key, $this->page, $this->url, $this->path, $this->routeName);
    }

    /**
     * Whether this entry is the place the reader is — or the place they are
     * inside of.
     *
     * @param  string|null  $key  The key the entry was registered under, when it has one.
     */
    public function isActive(NavigationItem $item, ?string $key = null): bool
    {
        $declared = $item->isActiveWhen($this);

        if ($declared !== null) {
            return $declared;
        }

        if ($key !== null && $this->key !== null && $key === $this->key) {
            return true;
        }

        return $this->sameUrl($item->getUrl());
    }

    /**
     * Whether this entry is the page being rendered, rather than an ancestor of it.
     *
     * The difference `aria-current` needs. A row for a resource stays active on
     * that resource's edit page — and calling that page "the current page" to a
     * screen reader, while the sub-navigation beside it also says so, is two
     * answers to one question.
     */
    public function isExactly(NavigationItem $item): bool
    {
        return $this->sameUrl($item->getUrl());
    }

    /**
     * What `aria-current` this entry should carry, if any.
     *
     * An owner-facing helper rather than three conditions in every view that
     * draws a row: `page` for the page itself, `true` for the branch it sits in,
     * and nothing at all otherwise.
     */
    public function ariaCurrent(NavigationItem $item, ?string $key = null): ?string
    {
        return match (true) {
            $this->isExactly($item) => 'page',
            $this->isActive($item, $key) => 'true',
            default => null,
        };
    }

    /**
     * Whether anything under this entry is where the reader is.
     *
     * Children carry no registered key — they are hand-written entries — so they
     * are matched by the rules that do not need one.
     */
    public function hasActiveChild(NavigationItem $item): bool
    {
        foreach ($item->getChildren() as $child) {
            if ($this->isActive($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the current request matches any of these patterns.
     *
     * Each is tried against **both** the path and the route name, because a
     * caller writing `activeWhen('settings/*')` and one writing
     * `activeWhen('admin.settings.*')` both mean the obvious thing, and asking
     * them to know which of the two this class compares would be a rule to
     * remember rather than a shorthand. A leading slash is trimmed, so
     * `/settings/*` works too.
     *
     * @param  array<int, string>  $patterns
     */
    public function matchesPatterns(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = ltrim($pattern, '/');

            if ($this->path !== null && Str::is($pattern, $this->path)) {
                return true;
            }

            if ($this->routeName !== null && Str::is($pattern, $this->routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL equality, minus the two differences that are never meant.
     *
     * A **trailing slash**, and a URL that is relative. The second is not an
     * edge case: `ResolvesPageUrls` answers with `route()`, which is absolute,
     * while an entry written by hand is usually `->url('/settings')` — so a
     * comparison of raw strings called them different on the page they both
     * point at. `to()` resolves a relative URL against the application and hands
     * an absolute one straight back, so both sides end up in the same shape.
     *
     * The query string is deliberately kept. Two entries that differ only by one
     * — an "All" and an "Archived" pointing at the same list — are two entries,
     * and folding them together would light both.
     *
     * An entry with no URL is never the current page: it is a registered thing
     * this zone does not route, and the row is drawn unlinked.
     */
    private function sameUrl(?string $url): bool
    {
        if ($url === null || $this->url === null) {
            return false;
        }

        return rtrim(url()->to($url), '/') === rtrim(url()->to($this->url), '/');
    }
}

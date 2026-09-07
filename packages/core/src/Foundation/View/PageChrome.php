<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

/**
 * Views a package needs rendered once per page, outside any page's own markup.
 *
 * The problem this solves is a dependency direction. A modal that has to exist
 * on every screen — a media picker something else opens, a confirmation host, a
 * command palette — cannot be mounted by the page that opens it, because the
 * *shell* is what renders once. But the shell (`wire-admin`) sits at the top of
 * the graph and knows nothing about the module packages beside it, so it cannot
 * name their views, and they cannot reach into its layout.
 *
 * So neither end names the other. A package registers a view here; the shell
 * renders whatever is registered:
 *
 *   PageChrome::add('wire-module-media::picker-modal');
 *
 * **Registered in a service provider's boot, once.** `add()` is idempotent by
 * view name, because a provider that runs twice — a package required by two
 * others, a test that boots the app again — must not put two copies of a modal
 * in the document, and two modals listening for one event both answer it.
 *
 * The registry holds *names*, not rendered markup: rendering happens in the
 * layout, inside the request, where the view can see the current user, the
 * current locale and everything else that is not true at boot.
 *
 * An application that renders its own layout instead of `wire-admin`'s can do
 * the same in one line — see the docs — and one that renders neither simply has
 * no chrome, which is a page without a picker rather than a page that breaks.
 *
 * **Regions, one registry.** A modal only has to exist, so it goes at the end of
 * the document (`BODY`, the default). A team switcher has to be *seen*, so it
 * goes in the top bar (`TOPBAR`). An entry that belongs to the person rather
 * than to the page — their profile, their way out — goes in their menu
 * (`USER_MENU`). All of them are the same dependency problem — a package that
 * cannot reach into a layout it does not know — and splitting them into three
 * registries would have been three answers to one question:
 *
 *   PageChrome::add('wire-module-users::team-switcher', PageChrome::TOPBAR);
 */
final class PageChrome
{
    /** The end of the document: modals, hosts, anything that only has to exist. */
    public const BODY = 'body';

    /**
     * The top bar, beside the search and the user menu.
     *
     * A separate region rather than a second registry, because the problem is
     * the same one and only the answer to "where" differs. A team switcher, a
     * tenant picker, an environment badge: things that have to be *seen* rather
     * than merely present, and that a module still cannot reach into the shell
     * to place.
     */
    public const TOPBAR = 'topbar';

    /**
     * Inside the signed-in user's own menu.
     *
     * The region that was missing, and its absence was visible: the shell draws
     * the avatar, the name and the dropdown, and then leaves the *contents* to a
     * `userMenu` slot — so every application wrote its own "Profile" link and
     * its own sign-out form, by hand, against packages it happened to have
     * installed. Two entries, in one place, that no application actually owns.
     *
     * The package that owns the profile page contributes the link to it, and the
     * package that owns authentication contributes the way out. The slot stays,
     * and stays first: an application's own entries are not a module's to sort
     * around.
     */
    public const USER_MENU = 'user-menu';

    /** @var array<string, array<int, array{view: string, sort: int}>> */
    private array $views = [];

    /**
     * Register a view to be rendered in the page chrome.
     *
     * Later registrations of the same name in the same region are ignored rather
     * than appended.
     *
     * `$sort` is what registration order cannot answer. Provider order in a
     * Laravel application is composer's discovery order — it is not a contract,
     * and the first region where two packages contribute at once made that
     * visible: "Sign out" above "Profile" reads as a bug, and neither package
     * can see the other to avoid it. Lower sorts first; equal sorts keep the
     * order they arrived in, so a region with one contributor never has to think
     * about this at all.
     */
    public function add(string $view, string $region = self::BODY, int $sort = 0): void
    {
        if ($view === '' || $this->has($view, $region)) {
            return;
        }

        $this->views[$region][] = ['view' => $view, 'sort' => $sort];
    }

    /**
     * Every view registered for a region, by sort and then by registration.
     *
     * @return array<int, string>
     */
    public function views(string $region = self::BODY): array
    {
        $entries = $this->views[$region] ?? [];

        // `usort` has been stable since PHP 8.0, which is what makes equal sorts
        // fall back to registration order without a second key to compare.
        usort($entries, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return array_column($entries, 'view');
    }

    public function has(string $view, string $region = self::BODY): bool
    {
        return in_array($view, array_column($this->views[$region] ?? [], 'view'), true);
    }
}

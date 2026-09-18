<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Routing\Zone;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The admin's own address — `/admin` — when no landing page claims it.
 *
 * Every page of the panel lives *under* its prefix, so the prefix itself used
 * to be a 404. That is the one address everybody types, the one a brand link
 * points at, and — once `wire:install` sets `fortify.home` to it — the one a
 * person lands on the moment they sign in. So it answers: it sends them to the
 * first page of the admin they can actually open.
 *
 * **First as the sidebar orders it.** The menu is the admin's own statement of
 * what comes first, so the entry reads {@see Workspace::navigation()} — groups
 * by their sort, entries by theirs, hidden ones gone — rather than keeping a
 * second idea of the order.
 *
 * **And one they may open.** An entry's visibility is its own closure, and most
 * pages guard themselves with `can:` middleware instead — the Users screen
 * shows in the menu and answers 403 to somebody without `users.viewAny`. A
 * redirect into a refusal is a worse landing than none, so the target route's
 * `can:` middleware is asked of the Gate first ({@see RouteAccess}), and an
 * entry that would refuse is passed over.
 *
 * A landing page (`routePrefix()` of `ConfiguresRoutes::ROOT`) owns the path
 * instead, and this is not registered at all — see {@see ResourceRoutes::all()}.
 */
final readonly class PanelEntry
{
    public function __construct(
        private Workspace $workspace,
        private RouteAccess $access,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $offered = false;

        foreach ($this->candidates(Zone::current()) as $url) {
            $offered = true;

            if ($this->access->allowsUrl($url, $request->user())) {
                return redirect()->to($url);
            }
        }

        // Nothing registered at all is a missing page; pages that all refuse
        // this person are a refusal, and saying so is what a 403 is for.
        throw new HttpException($offered ? 403 : 404);
    }

    /**
     * Every linked entry, in the order the sidebar draws them.
     *
     * A parent that is only a heading contributes its children instead.
     *
     * @return iterable<int, string>
     */
    private function candidates(?string $zone): iterable
    {
        foreach ($this->workspace->navigation($zone, linkedOnly: false) as $group) {
            foreach ($group->getItems() as $item) {
                yield from $this->urlsOf($item);
            }
        }
    }

    /** @return iterable<int, string> */
    private function urlsOf(NavigationItem $item): iterable
    {
        if ($item->getUrl() !== null) {
            yield $item->getUrl();
        }

        foreach ($item->getChildren() as $child) {
            if ($child->getUrl() !== null) {
                yield $child->getUrl();
            }
        }
    }
}

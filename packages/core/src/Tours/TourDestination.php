<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/**
 * Where a tour's pages are, for the two callers that need to send somebody there.
 *
 * ## One owner, because there are now two askers
 *
 * A step declared with {@see TourStep::on()} needs the address of the page it
 * lives on, so the browser can walk a tour across pages; and
 * {@see TourState::replayNow()} needs the address of the page a tour *starts*
 * on, so a "show me that again" button somewhere else can put somebody in front
 * of it. Both questions are "where is this tour's page, and may this person open
 * it", and the second arrived after the first had already been answered inside
 * {@see TourHost}. Answering it a second time there would have been the copy
 * that diverges: the authorization check is one line, and a copy missing it
 * walks somebody into a 403 rather than skipping a step.
 *
 * ## Both rules live here, and neither is negotiable
 *
 * **The address comes from the router, never from a string.**
 * {@see ResolvesPageUrls} is the same owner a menu entry and a search result ask,
 * so a tour follows the routes when they move and can never name one. It answers
 * null for a page that is not routed in that zone, which is a real answer: the
 * step is then skipped like an element that is not on the page.
 *
 * **A step nobody may open is not a step.** {@see AuthorizesUrls} puts the
 * page's own `can:` middleware to the Gate. Somebody without access gets a tour
 * one step shorter, rather than a walkthrough that leads into a 403 — and a
 * replay trigger for a tour they may not see hands back null rather than a
 * redirect.
 *
 * Nothing here reads the current route. The zone is passed in, for the reason
 * {@see TourState} records at length: during a Livewire update the current route
 * is Livewire's own endpoint, so a class that asked would answer null on exactly
 * the requests a test does not make.
 */
final class TourDestination
{
    public function __construct(
        private readonly ResolvesPageUrls $urls,
        private readonly AuthorizesUrls $access,
    ) {}

    /**
     * Where a step that is not on the page being rendered is, or null.
     *
     * Its own {@see TourStep::on()} page, or — for a step on the tour's starting
     * page, seen from another one — the page the tour starts on. A tour
     * constrained by nothing narrower than a zone has no single page to return
     * to, and simply does not offer the way back.
     */
    public function of(Tour $tour, TourStep $step, ?string $zone, ?Authenticatable $user): ?string
    {
        [$resource, $page] = $step->isElsewhere()
            ? [$step->getResource(), $step->getPage()]
            : ($tour->home() ?? [null, null]);

        return $this->urlFor($resource, $page, $zone, $user);
    }

    /**
     * Where this tour begins, with the tour already running when it gets there.
     *
     * The address carries the same two query parameters a step on another page
     * travels by, so the page that receives it renders the chrome for a tour it
     * would not otherwise have been claimed by, and the browser strips them
     * again once it has read them. The server still checks the request against
     * {@see TourState::resuming()} on arrival, so this is a convenience for the
     * caller and not a way past anything.
     *
     * Null when the tour names no page of its own — an unconstrained tour, or
     * one scoped only by zone, has no single screen to be sent to — or when this
     * person may not open the one it names.
     */
    public function start(Tour $tour, ?Authenticatable $user): ?string
    {
        [$resource, $page] = $tour->home() ?? [null, null];

        $url = $this->urlFor($resource, $page, $tour->startZone(), $user);

        if ($url === null) {
            return null;
        }

        return $url
            .(str_contains($url, '?') ? '&' : '?')
            .http_build_query([
                TourHost::QUERY_TOUR => $tour->getId(),
                TourHost::QUERY_STEP => 0,
            ]);
    }

    /** One registered page's address, if it is routed and this person may open it. */
    private function urlFor(?string $resource, ?string $page, ?string $zone, ?Authenticatable $user): ?string
    {
        if ($resource === null || $page === null) {
            return null;
        }

        $url = $this->urls->urlFor($resource, $page, [], $zone);

        return $url !== null && $this->access->allowsUrl($url, $user) ? $url : null;
    }
}

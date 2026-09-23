<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Foundation\Support\MobileSheet;

/**
 * What the host view needs, resolved in PHP.
 *
 * Rendering Rule 1: the view outputs markup and nothing else, so the questions
 * "which tour" and "what does the browser need to run it" are answered here and
 * handed over as data. A composer binds the result onto
 * `wire-core::tours.host`; the view never resolves out of the container.
 *
 * ## This is the one place the route is read
 *
 * {@see Zone}'s docblock records the measurement:
 * `Route::currentRouteName()` answers `livewire.update` during a Livewire round
 * trip, so its three readers are right on a page's first render and null on
 * every request after it. The host view is rendered by the *layout*, which is
 * exactly and only the full-page render — so this is the right place, and
 * nothing downstream may ask again. {@see TourState::match()} takes the three
 * values as arguments for that reason.
 *
 * ## The payload is a value, not a component
 *
 * Everything the browser needs is serialisable: the tour's id, its steps'
 * selectors and copy, and the breakpoint below which no tour runs. That keeps
 * the chrome a plain Alpine object over data rather than a JS bundle, which is
 * what lets this feature ship without adding a byte to any asset.
 */
final class TourHost
{
    /**
     * The query a page is opened with when a tour carries on there.
     *
     * Plain query parameters rather than anything stored, because the page that
     * receives them is rendered by the server and has to know *before* it
     * renders that a tour is coming: the host is only drawn for a tour that
     * claims the page, and a page two steps into a walkthrough is claimed by
     * nothing else. The browser strips them again once the tour has read them.
     */
    public const QUERY_TOUR = 'wire-tour';

    public const QUERY_STEP = 'wire-tour-step';

    public function __construct(
        private readonly TourState $state,
        private readonly Auth $auth,
        private readonly Request $request,
        private readonly TourDestination $destination,
    ) {}

    /**
     * The tour to run on the page being rendered, or null when there is none.
     *
     * Null is the overwhelmingly common answer — no tour registered, or none
     * that claims this screen, or one the person has already acknowledged — and
     * the view renders nothing at all for it.
     */
    public function current(): ?Tour
    {
        // A tour carrying on from another page wins over one that merely starts
        // here: somebody in the middle of a walkthrough should finish it before
        // a second one opens underneath it.
        $resuming = $this->resumingTour();

        if ($resuming !== null) {
            return $resuming;
        }

        return $this->state->match(
            Zone::current(),
            Zone::currentKey(),
            Zone::currentPage(),
            $this->auth->guard()->user(),
        );
    }

    /**
     * The tour a "replay" entry would restart on this page, or null.
     *
     * Ignores the ledger, which is the whole point: the tour worth offering
     * again is precisely the one already acknowledged, and {@see current()}
     * stops answering the moment that happens.
     *
     * Kept separate from `current()` rather than rendering the chrome for
     * acknowledged tours too: the chrome is markup on every page for ever after
     * one tour is finished, and a replay is rare enough to be worth a page load
     * instead.
     */
    public function replayable(): ?Tour
    {
        return $this->state->claiming(Zone::current(), Zone::currentKey(), Zone::currentPage());
    }

    /**
     * Everything the browser needs to run a tour, as plain data.
     *
     * `breakpoint` is {@see MobileSheet::px()} rather than a literal, because
     * the number below which a panel stops being a floating panel is the
     * application's to configure and already has an owner. Below it the tour's
     * panel docks to the bottom of the screen, and two sources for that line
     * would drift.
     *
     * Each step says whether it is on **this** page (`here`) and, when it is
     * not, the address of the page it is on (`url`), so the browser can walk a
     * tour across pages without knowing a single route. A step whose page
     * resolves to nothing — not routed in this zone — gets no URL, and the
     * browser skips it the way it skips an element that is not on screen. So
     * does one whose page this person may not open.
     *
     * `resume` is the step a navigated-to page carries on at, or null for a
     * tour starting from its first step. `from` is the step somebody reached
     * before they left the tour on an earlier visit, or null — a starting point
     * the browser settles against what this page has, where `resume` is a
     * promise the server already checked.
     *
     * `welcome` is the block the tour opens with, or null for a tour that starts
     * by pointing at something. Its labels are resolved here rather than on
     * {@see TourWelcome}, which is registered at boot: a translation resolved
     * then would be in whatever locale the console had. `later` is null when the
     * tour allows no postponement, and the browser renders no button for it.
     *
     * @return array{id: string, breakpoint: float, resume: int|null, from: int|null, welcome: array{heading: string|null, text: string|null, start: string, later: string|null, view: string|null}|null, steps: array<int, array{selector: string, heading: string|null, text: string|null, placement: string, here: bool, url: string|null}>}
     */
    public function payload(Tour $tour): array
    {
        $zone = Zone::current();
        $resource = Zone::currentKey();
        $page = Zone::currentPage();

        return [
            'id' => $tour->getId(),
            'breakpoint' => MobileSheet::px(),
            'welcome' => $this->welcome($tour),
            'resume' => $resume = $this->resumingTour()?->getId() === $tour->getId() ? $this->requestedStep() : null,
            'from' => $resume === null ? $this->state->reached($tour, $this->auth->guard()->user()) : null,
            'steps' => array_map(
                fn (TourStep $step): array => [
                    'selector' => $step->getSelector(),
                    'heading' => $step->getHeading(),
                    'text' => $step->getText(),
                    'placement' => $step->getPlacement(),
                    'here' => $here = $tour->stepIsHere($step, $zone, $resource, $page),
                    'url' => $here ? null : $this->urlOf($tour, $step, $zone),
                ],
                $tour->getSteps(),
            ),
        ];
    }

    /** The tour this request was sent to carry on, when it is one that may. */
    private function resumingTour(): ?Tour
    {
        $id = $this->request->query(self::QUERY_TOUR);
        $step = $this->requestedStep();

        if (! is_string($id) || $id === '' || $step === null) {
            return null;
        }

        return $this->state->resuming(
            $id,
            $step,
            Zone::current(),
            Zone::currentKey(),
            Zone::currentPage(),
            $this->auth->guard()->user(),
        );
    }

    private function requestedStep(): ?int
    {
        $step = $this->request->query(self::QUERY_STEP);

        return is_string($step) && ctype_digit($step) ? (int) $step : null;
    }

    /**
     * The welcome block as the browser needs it, or null when there is none.
     *
     * The "Later" label is dropped rather than merely unused when the tour
     * allows no postponement: what the payload does not carry, the markup
     * cannot render, so the choice is absent from the page rather than present
     * and ignored.
     *
     * @return array{heading: string|null, text: string|null, start: string, later: string|null, view: string|null}|null
     */
    private function welcome(Tour $tour): ?array
    {
        $welcome = $tour->getWelcome();

        if ($welcome === null) {
            return null;
        }

        $postponable = ($tour->getPostponeLimit() ?? TourState::configuredPostponeLimit()) > 0;

        return [
            'heading' => $welcome->getHeading(),
            'text' => $welcome->getText(),
            'start' => $welcome->getStart() ?? __('wire-core::messages.tour_start'),
            'later' => $postponable ? ($welcome->getLater() ?? __('wire-core::messages.tour_later')) : null,
            'view' => $welcome->getView(),
        ];
    }

    /**
     * Where a step that is not on this page is — {@see TourDestination}, which
     * owns the question because a replay trigger elsewhere now asks it too.
     */
    private function urlOf(Tour $tour, TourStep $step, ?string $zone): ?string
    {
        return $this->destination->of($tour, $step, $zone, $this->auth->guard()->user());
    }
}

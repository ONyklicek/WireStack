<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Factory as Auth;
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
    public function __construct(
        private readonly TourState $state,
        private readonly Auth $auth,
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
     * application's to configure and already has an owner. A tour does not
     * become a sheet there — it does not run at all — but the line it stops at
     * is the same line, and two sources for it would drift.
     *
     * @return array{id: string, breakpoint: float, steps: array<int, array{selector: string, heading: string|null, text: string|null, placement: string}>}
     */
    public function payload(Tour $tour): array
    {
        return [
            'id' => $tour->getId(),
            'breakpoint' => MobileSheet::px(),
            'steps' => array_map(
                static fn (TourStep $step): array => [
                    'selector' => $step->getSelector(),
                    'heading' => $step->getHeading(),
                    'text' => $step->getText(),
                    'placement' => $step->getPlacement(),
                ],
                $tour->getSteps(),
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The round trips a tour makes: recording how far somebody got, and that they
 * are done with it.
 *
 * Everything else about a tour happens in the browser over data the page
 * already carried — which step is showing, where the panel sits, whether the
 * next anchor exists. Deciding is not worth a request. *Remembering* is, and
 * this is it.
 *
 * ## Why the id is a property and not an argument
 *
 * `acknowledge()` is a public Livewire method, so the browser decides when it is
 * called and with what. It takes nothing: the tour being acknowledged is the one
 * this component was mounted with, held server-side across the round trip in a
 * property Livewire signs. A method that took an id would let any page
 * acknowledge any tour for the person looking at it — harmless in isolation, and
 * exactly the shape that stops being harmless later.
 *
 * ## Finishing and skipping are the same call
 *
 * The browser's event carries which one happened, and nothing here reads it.
 * Somebody who skipped has decided about this tour as firmly as somebody who
 * finished it, and a tour that comes back after a skip is the worst version of
 * this feature. Recording them differently would only create a state worth
 * treating differently later.
 *
 * ## Guests
 *
 * `null` is a legitimate user here. The guest preference driver defaults to the
 * session, so an unauthenticated person on a public page gets the same "seen"
 * behaviour for as long as their session lasts, and nothing throws. A tour that
 * blew up for a guest would take the page's whole Livewire layer with it.
 */
final class TourAcknowledgement extends Component
{
    public string $tourId = '';

    public function mount(string $tourId): void
    {
        $this->tourId = $tourId;
    }

    /**
     * Record that this person is done with the tour, however they finished it.
     *
     * An id the registry does not know is ignored rather than refused — the tour
     * may have been removed between the page rendering and the click, and there
     * is nothing to record and nothing to complain about. {@see TourState}
     * owns that decision.
     */
    public function acknowledge(TourState $state, Auth $auth): void
    {
        $state->acknowledge($this->tourId, $auth->guard()->user());
    }

    /**
     * Record that this person chose "Later" in the welcome block.
     *
     * Takes nothing, for the reason `acknowledge()` does: the tour is the one
     * this component was mounted with. {@see TourState::postpone()} owns what
     * "later" costs — a tour whose author allowed no postponement records
     * nothing here, so the browser cannot invent the choice by dispatching the
     * event.
     */
    public function postpone(TourState $state, Auth $auth): void
    {
        $state->postpone($this->tourId, $auth->guard()->user());
    }

    /**
     * Record how far this person got, so a tour they leave halfway reopens
     * there. The step is the browser's to name and {@see TourState::reach()}'s
     * to bound; the tour is still this component's own.
     */
    public function reach(int $step, TourState $state, Auth $auth): void
    {
        $state->reach($this->tourId, $step, $auth->guard()->user());
    }

    public function render(): View
    {
        return view('wire-core::tours.acknowledgement');
    }
}

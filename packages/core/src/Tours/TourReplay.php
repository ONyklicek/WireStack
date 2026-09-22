<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tours;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireCore\Foundation\View\PageChrome;

/**
 * "Show me that again" — the manual way back into a tour somebody finished.
 *
 * Contributed to {@see PageChrome::USER_MENU},
 * because a walkthrough belongs to the person rather than to the page, next to
 * the other entries that are theirs.
 *
 * It is mounted only where a tour claims the screen — the view registered with
 * `PageChrome` decides that, so an application with no tours never pays for a
 * component here at all. The id it works on is handed to it for the same
 * reason the host's is: the page render is the only moment the route can be
 * read, and this component outlives that moment.
 *
 * ## Why it reloads instead of restarting in place
 *
 * Forgetting is a write, and the chrome that would run the tour is rendered by
 * the *layout* — outside this component, and outside anything a Livewire
 * response morphs. So there is no version of this that both forgets and starts
 * without a fresh page render, short of keeping the whole chrome on every page
 * for ever on the chance that somebody replays. A replay is rare; a page load
 * is the honest price for it.
 *
 * ## The browser reloads itself; no address is stored
 *
 * The obvious implementation keeps the page's URL in a property at mount and
 * redirects to it afterwards — and that property would be **writable from the
 * browser**, because every public Livewire property is. An unvalidated redirect
 * target handed back by the client is an open redirect, and "the user can only
 * redirect themselves" stops being true the moment somebody is handed a link
 * that sets it.
 *
 * Validating it would work; not having it is better. The page that needs
 * re-rendering is the one the browser is already on, so the browser reloads
 * itself and the server never learns or repeats an address. The same reason
 * {@see Zone} cannot be asked on a round trip is why nothing here tries to
 * reconstruct one either: on this request the current URL is Livewire's update
 * endpoint.
 */
final class TourReplay extends Component
{
    public string $tourId = '';

    public function mount(string $tourId): void
    {
        $this->tourId = $tourId;
    }

    /**
     * Forget this tour for this person, then have the page render again with it.
     */
    public function replay(TourState $state, Auth $auth): void
    {
        $state->replay($this->tourId, $auth->guard()->user());

        $this->dispatch('wire-tour:forgotten');
    }

    public function render(): View
    {
        return view('wire-core::tours.replay-menu-item');
    }
}

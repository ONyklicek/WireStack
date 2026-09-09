<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use NyonCode\WireCore\WireCoreServiceProvider;

/**
 * Runs an action that a display component is carrying.
 *
 * A boundary object, and the boundary is a real one. `Actions` and `Widgets`
 * are sibling L2 modules and neither may import the other (ADR 0025), yet a
 * widget with a "Refresh" button in its header has to end up running an
 * `Action`'s callback. The module-layer rule names the way across in so many
 * words — write a contract in `Foundation/Contracts/` — and this is it.
 *
 * Deliberately not on the host. `InteractsWithActions` is a trait and a trait
 * cannot implement an interface, so a host-side contract would have to be
 * declared by every component that composes it, and the failure mode of
 * forgetting is a button that silently does nothing. Bound in the container
 * instead ({@see WireCoreServiceProvider}), so a surface
 * asks for the capability rather than for a particular host to have remembered
 * to advertise it.
 *
 * ## What "run" means here, and what it does not
 *
 * The callback, with the context the surface found the action in. Not the action
 * *lifecycle*: no mounting, no modal, no confirmation, no form. That is the same
 * bargain `InteractsWithActions::callInfolistAction()` already strikes for the
 * buttons an infolist entry carries, and for the same reason — these surfaces
 * are rebuilt from their declaration on every request, so the button carries a
 * name rather than a closure and there is nothing mounted to resume into.
 *
 * An action that needs to *ask* before it acts therefore does not belong on one
 * of these surfaces. Put it on the page, where a host composing `WithActions`
 * owns a modal host, and leave the widget the things that just happen.
 */
interface RunsComponentActions
{
    /**
     * @param  ActionContract  $action  the action the surface resolved by name
     * @param  array<string, mixed>  $context  named arguments offered to the callback —
     *                                         `livewire`, `component` and whatever the
     *                                         surface knows (a widget passes `widget`)
     */
    public function runComponentAction(ActionContract $action, array $context = []): void;
}

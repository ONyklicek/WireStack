<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Drivers;

use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

/**
 * The default driver: a live toast, and a flash for the toast that cannot be one.
 *
 * Both, deliberately, because a notification raised on the way out of a page has
 * nowhere to be shown. The browser event reaches a document that is about to be
 * replaced — an action with `successRedirect()`, a create page landing on the
 * record it just filed — and the toast dies with it. The flash is what crosses
 * that boundary; `<x-wire-notifications::toast-container />` renders it on
 * arrival.
 *
 * **What stops it from arriving twice** is Livewire's own doing rather than a
 * flag here: `SupportRedirects` forgets everything flashed during an update that
 * did *not* redirect (`session()->forget(session()->get('_flash.new'))`), so a
 * save that stayed put has already shown its toast as an event and leaves
 * nothing behind for the next page to show again. Only a redirect keeps it —
 * which is exactly the case the flash exists for.
 *
 * The payload is the **whole** notification either way, so a toast that arrives
 * after a redirect carries the same title, icon, duration and actions as the one
 * that would have appeared without it.
 */
class SessionDriver implements NotificationDriver
{
    public function __construct(
        public string $sessionKey = 'table-notification',
        public string $eventName = 'table-notification',
    ) {}

    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        session()->flash($this->sessionKey, $notification->toArray());

        if ($livewireComponent && method_exists($livewireComponent, 'dispatch')) {
            // Forward the full payload (title, duration, actions, …) so rich
            // toasts survive the server round-trip, not just type + message.
            $livewireComponent->dispatch($this->eventName, ...$notification->toArray());
        }
    }
}

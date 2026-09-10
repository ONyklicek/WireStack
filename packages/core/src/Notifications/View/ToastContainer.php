<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;

/**
 * Toast notification container Blade component.
 *
 * Renders the Alpine.js-powered toast notification container that
 * listens for Livewire events and displays toast notifications.
 *
 * It also shows the one kind of toast an event cannot deliver: the notification
 * raised by a request that then redirected, which {@see SessionDriver} flashed
 * on its way out. {@see flashed()} is that side of it.
 *
 * Usage:
 *   <x-wire-notifications::toast-container />
 *   <x-wire-notifications::toast-container position="top-right" :duration="5000" />
 *   <x-wire-notifications::toast-container stack />               {{-- collapse into a pile, fan out on hover --}}
 *   <x-wire-notifications::toast-container :progress="false" />   {{-- hide the per-toast countdown bar --}}
 *   <x-wire-notifications::toast-container :max="5" />            {{-- cap visible toasts, overflow into "+N more" --}}
 */
class ToastContainer extends Component
{
    public function __construct(
        public string $position = 'top-right',
        public int $duration = 4000,
        public string $eventName = 'table-notification',
        // Read rather than dispatched: the session key the driver flashed under.
        // Its own prop because the two are separate settings on the driver, so
        // an application that renamed one can still match it here.
        public string $sessionKey = 'table-notification',
        public bool $stack = false,
        public bool $progress = true,
        public int $max = 0,
    ) {}

    public function positionClasses(): string
    {
        return match ($this->position) {
            'top-left' => 'top-4 left-4',
            'top-center' => 'top-4 left-1/2 -translate-x-1/2',
            'top-right' => 'top-4 right-4',
            'bottom-left' => 'bottom-4 left-4',
            'bottom-center' => 'bottom-4 left-1/2 -translate-x-1/2',
            'bottom-right' => 'bottom-4 right-4',
            default => 'top-4 right-4',
        };
    }

    /**
     * Whether the container is anchored to the top edge.
     *
     * Drives the stack fan-out direction and expanded stacking order so the
     * newest toast always sits closest to the anchor.
     */
    public function topAnchored(): bool
    {
        return str_starts_with($this->position, 'top');
    }

    /**
     * The toast a redirect carried here, with a render-scoped id to show it once.
     *
     * Reading does not consume: flashed data is already ageing out and the
     * request that reads it is the last one to see it, so there is nothing to
     * clear. The id is what keeps the same markup from showing twice — Livewire's
     * navigate caches a visited page and re-initialises Alpine over the restored
     * HTML, which without this would re-raise the toast on every press of the
     * back button.
     *
     * @return array{id: string, payload: array<string, mixed>}|null
     */
    public function flashed(): ?array
    {
        // A container can be rendered where no session exists — a console
        // render, a mail preview — and asking for one there is a fatal rather
        // than an empty answer.
        if (! app()->has('session.store')) {
            return null;
        }

        // Never during a Livewire update, and this one was measured rather than
        // reasoned: `flash()` writes into the *current* session too, so a
        // container that sits inside a component re-renders mid-update, reads
        // the notification that update just sent, and shows a second copy of the
        // toast the event is already delivering. The flash is addressed to the
        // next document; an update is still this one.
        if (Livewire::isLivewireRequest()) {
            return null;
        }

        $payload = session()->get($this->sessionKey);

        if (! is_array($payload) || ($payload['message'] ?? null) === null) {
            return null;
        }

        return ['id' => uniqid('toast-', true), 'payload' => $payload];
    }

    public function render(): View
    {
        return view('wire-core::notifications.toast-container');
    }
}

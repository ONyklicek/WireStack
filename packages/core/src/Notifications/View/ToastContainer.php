<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Toast notification container Blade component.
 *
 * Renders the Alpine.js-powered toast notification container that
 * listens for Livewire events and displays toast notifications.
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

    public function render(): View
    {
        return view('wire-core::notifications.toast-container');
    }
}

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
 */
class ToastContainer extends Component
{
    public function __construct(
        public string $position = 'top-right',
        public int $duration = 4000,
        public string $eventName = 'table-notification',
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

    public function render(): View
    {
        return view('wire-core::notifications.toast-container');
    }
}

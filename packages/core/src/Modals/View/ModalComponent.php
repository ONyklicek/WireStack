<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Blade component: <x-wire-modals::modal>
 *
 * A general-purpose modal dialog with Alpine.js transitions.
 *
 * Usage:
 *   <x-wire-modals::modal
 *       wire:model="showModal"
 *       heading="Edit Record"
 *       description="Update the details."
 *       width="lg"
 *   >
 *       {{-- Modal body content --}}
 *   </x-wire-modals::modal>
 */
class ModalComponent extends Component
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public ?string $icon = null,
        public string $iconColor = 'gray',
        public ?string $maxHeight = null,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public bool $fullScreenOnMobile = false,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
    ) {}

    public function widthClass(): string
    {
        return match ($this->width) {
            'sm' => 'sm:max-w-sm',
            'lg' => 'sm:max-w-lg',
            'xl' => 'sm:max-w-xl',
            '2xl' => 'sm:max-w-2xl',
            '3xl' => 'sm:max-w-3xl',
            '4xl' => 'sm:max-w-4xl',
            '5xl' => 'sm:max-w-5xl',
            '6xl' => 'sm:max-w-6xl',
            '7xl' => 'sm:max-w-7xl',
            'full' => 'sm:max-w-full',
            default => 'sm:max-w-md',
        };
    }

    public function iconBgClass(): string
    {
        return match ($this->iconColor) {
            'danger' => 'bg-red-100 dark:bg-red-900/30',
            'success' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'info' => 'bg-blue-100 dark:bg-blue-900/30',
            'warning' => 'bg-amber-100 dark:bg-amber-900/30',
            'primary' => 'bg-primary-100 dark:bg-primary-900/30',
            default => 'bg-gray-100 dark:bg-gray-700',
        };
    }

    public function iconColorClass(): string
    {
        return match ($this->iconColor) {
            'danger' => 'text-red-600 dark:text-red-400',
            'success' => 'text-emerald-600 dark:text-emerald-400',
            'info' => 'text-blue-600 dark:text-blue-400',
            'warning' => 'text-amber-600 dark:text-amber-400',
            'primary' => 'text-primary-600 dark:text-primary-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    public function render(): View
    {
        return view('wire-core::modals.modal');
    }
}

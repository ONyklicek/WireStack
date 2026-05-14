<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Support\Trans;

/**
 * Blade component: <x-wire-modals::confirmation>
 *
 * A confirmation dialog with icon, heading, description, and action buttons.
 *
 * Usage:
 *   <x-wire-modals::confirmation
 *       wire:model="showConfirmation"
 *       heading="Delete record?"
 *       description="This action cannot be undone."
 *       icon="trash"
 *       icon-color="danger"
 *       submit-label="Delete"
 *       cancel-label="Cancel"
 *       color="danger"
 *   />
 */
class ConfirmationComponent extends Component
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public ?string $icon = null,
        public string $iconColor = 'warning',
        public ?string $submitLabel = null,
        public ?string $cancelLabel = null,
        public ?string $color = null,
        public bool $isDanger = false,
        public bool $isInformative = false,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public ?string $id = null,
    ) {
        $this->submitLabel ??= Trans::get('wire-core::actions.confirm_submit');
        $this->cancelLabel ??= Trans::get('wire-core::actions.confirm_cancel');

        if ($this->isDanger && $this->color === null) {
            $this->color = 'danger';
        }
    }

    public function widthClass(): string
    {
        return match ($this->width) {
            'sm' => 'sm:max-w-sm',
            'lg' => 'sm:max-w-lg',
            'xl' => 'sm:max-w-xl',
            '2xl' => 'sm:max-w-2xl',
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
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    public function submitButtonClasses(): string
    {
        $base = 'inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto';

        $colorClasses = match ($this->color) {
            'danger' => 'bg-red-600 hover:bg-red-500 focus:ring-red-500',
            'success' => 'bg-emerald-600 hover:bg-emerald-500 focus:ring-emerald-500',
            'warning' => 'bg-amber-500 hover:bg-amber-400 focus:ring-amber-500',
            default => 'bg-primary-600 hover:bg-primary-500 focus:ring-primary-500',
        };

        return "{$base} {$colorClasses}";
    }

    public function render(): View
    {
        return view('wire-core::modals.confirmation');
    }
}

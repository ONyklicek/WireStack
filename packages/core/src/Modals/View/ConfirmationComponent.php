<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

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
    use HasColor;

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
        public ?string $closeAction = null,
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

    protected function getColor(): string
    {
        return $this->color ?? 'primary';
    }

    public function iconBgClass(): string
    {
        return self::getModalIconBgClass($this->iconColor);
    }

    public function iconColorClass(): string
    {
        return self::getModalIconTextClass($this->iconColor);
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

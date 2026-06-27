<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

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
        return HasModalProperties::getMaxWidthClass($this->width);
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

        // Delegate the hue map to the canonical owner so this footer stays in
        // sync with the table action modal footers instead of re-encoding it.
        return "{$base} ".self::getModalSubmitButtonClasses($this->color ?? 'primary');
    }

    public function render(): View
    {
        return view('wire-core::modals.confirmation');
    }
}

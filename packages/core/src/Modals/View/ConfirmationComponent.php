<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Modals\Support\ConfirmationStyle;

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
        public ?string $closeAction = null,
        public ?int $zIndex = null,
    ) {
        $this->submitLabel ??= Trans::get('wire-core::actions.confirm_submit');
        $this->cancelLabel ??= Trans::get('wire-core::actions.confirm_cancel');

        if ($this->isDanger && $this->color === null) {
            $this->color = 'danger';
        }
    }

    public function style(): ConfirmationStyle
    {
        return new ConfirmationStyle(
            width: $this->width,
            iconColor: $this->iconColor,
            color: $this->color,
        );
    }

    public function render(): View
    {
        return view('wire-core::modals.confirmation', ['style' => $this->style()]);
    }
}

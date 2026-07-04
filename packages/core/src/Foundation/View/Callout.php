<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Standalone callout: <x-wire::callout color="warning" heading="…" icon="…">…</x-wire::callout>.
 *
 * Slot-based counterpart to Foundation\Schema\Callout; renders the shared
 * callout partial with the slot as its body.
 */
class Callout extends Component
{
    public string $colorClasses;

    public function __construct(
        public string $color = 'info',
        public ?string $heading = null,
        public ?string $icon = null,
        public bool $dismissible = false,
    ) {
        $this->colorClasses = HasColor::getAlertColorClasses($color);
    }

    public function render(): View
    {
        return view('wire-core::foundation.callout');
    }
}

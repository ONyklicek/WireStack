<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Standalone empty state: <x-wire::empty-state icon="…" heading="…" description="…">
 * {{-- optional action buttons --}}</x-wire::empty-state>.
 *
 * Slot-based counterpart to Foundation\Schema\EmptyState; the slot becomes the
 * action row of the shared empty-state partial.
 */
class EmptyState extends Component
{
    public function __construct(
        public ?string $icon = null,
        public ?string $heading = null,
        public ?string $description = null,
    ) {}

    public function render(): View
    {
        return view('wire-core::foundation.empty-state');
    }
}

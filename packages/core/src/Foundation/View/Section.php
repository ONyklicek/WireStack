<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Standalone section card: <x-wire::section heading="…" description="…">…</x-wire::section>.
 */
class Section extends Component
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
    ) {}

    public function render(): View
    {
        return view('wire-core::foundation.section');
    }
}

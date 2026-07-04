<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Standalone wizard: <x-wire::wizard><x-wire::step label="…">…</x-wire::step>…</x-wire::wizard>.
 *
 * Client-side (Alpine) step navigation with a numbered indicator and Back/Next
 * controls; child steps self-register their label. State only — no Livewire
 * roundtrip or per-step validation (use action-modal wizards for that).
 */
class Wizard extends Component
{
    public function __construct(public int $current = 0) {}

    public function render(): View
    {
        return view('wire-core::foundation.wizard');
    }
}

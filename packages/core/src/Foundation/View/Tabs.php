<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Standalone tabs: <x-wire::tabs><x-wire::tab label="…">…</x-wire::tab>…</x-wire::tabs>.
 *
 * Client-side (Alpine) tab switching; child panels self-register their label so
 * the tab bar renders itself. State only — no Livewire roundtrip.
 */
class Tabs extends Component
{
    public function __construct(public int $active = 0) {}

    public function render(): View
    {
        return view('wire-core::foundation.tabs');
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;

/**
 * Page A of the SPA-navigation fixture: a page with nothing on it.
 *
 * The point of this preview is what it does NOT render. No table, no dropdown,
 * no sortable surface — so none of the packages' pre-bundled controllers
 * (wireRecordSelection, wireRecordActions, wireDropdown & friends, wireSortable)
 * are injected into this document. Livewire and Alpine boot here, and
 * `alpine:init` fires here, once, with none of those bundles present to hear it.
 *
 * From here a `wire:navigate` link goes to {@see SpaTablePreview}, which is
 * where the bundles first arrive. `workbench/scripts/verify-spa-navigate.mjs`
 * drives that hop.
 */
class SpaPlainPreview extends Component
{
    public string $variant = 'plain';

    /**
     * A trivial server round trip, so the driver can prove Livewire itself is
     * alive on this page before it navigates away from it.
     */
    public int $pings = 0;

    public function mount(string $variant = 'plain'): void
    {
        $this->variant = $variant;
    }

    public function ping(): void
    {
        $this->pings++;
    }

    public function render()
    {
        return view('livewire.previews.spa-plain-preview');
    }
}

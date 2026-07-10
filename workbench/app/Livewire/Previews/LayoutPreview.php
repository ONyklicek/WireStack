<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;

class LayoutPreview extends Component
{
    public string $variant = 'tabs-wizard';

    public function mount(string $variant = 'tabs-wizard'): void
    {
        $this->variant = $variant;
    }

    public function render()
    {
        return view('livewire.previews.layout-preview');
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Support\MobileSheet;

/**
 * Blade component: <x-wire::dropdown>...</x-wire::dropdown>
 */
class Dropdown extends Component
{
    public bool $sheetOnMobile;

    public string $breakpoint;

    public function __construct(
        public string $position = 'bottom-end',
        public string $width = 'w-48',
        public ?string $trigger = null,
        ?bool $sheetOnMobile = null,
        ?string $breakpoint = null,
    ) {
        // Explicit props win; otherwise the global config defaults apply.
        $this->sheetOnMobile = $sheetOnMobile ?? (bool) config('wire-core.mobile.sheet', true);
        $this->breakpoint = MobileSheet::breakpoint($breakpoint);
    }

    public function render(): View
    {
        return view('wire-core::foundation.dropdown');
    }
}

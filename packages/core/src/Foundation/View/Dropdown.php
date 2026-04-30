<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Blade component: <x-wire::dropdown>...</x-wire::dropdown>
 */
class Dropdown extends Component
{
    public function __construct(
        public string $position = 'bottom-end',
        public string $width = 'w-48',
        public ?string $trigger = null,
    ) {}

    public function render(): View
    {
        return view('wire-core::foundation.dropdown');
    }
}

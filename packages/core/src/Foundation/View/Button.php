<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Blade component: <x-wire::button color="primary" size="sm">Submit</x-wire::button>
 */
class Button extends Component
{
    public function __construct(
        public string $color = 'primary',
        public string $size = 'sm',
        public bool $outlined = false,
        public ?string $icon = null,
        public ?string $iconPosition = 'before',
        public ?string $href = null,
        public bool $disabled = false,
        public string $type = 'button',
    ) {}

    public function render(): View
    {
        return view('wire-core::foundation.button');
    }
}

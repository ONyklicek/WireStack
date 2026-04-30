<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Blade component: <x-wire::badge color="success">Active</x-wire::badge>
 */
class Badge extends Component
{
    use HasColor;

    public string $colorClasses;

    public function __construct(
        public string $color = 'gray',
        public ?string $icon = null,
        public string $size = 'sm',
    ) {
        $this->colorClasses = self::getBadgeColorClasses($color);
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function render(): View
    {
        return view('wire-core::foundation.badge');
    }
}

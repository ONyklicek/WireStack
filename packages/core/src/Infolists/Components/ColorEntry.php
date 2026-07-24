<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use NyonCode\WireCore\Foundation\Concerns\CanBeCopyable;

/**
 * Color entry — renders the state as a color swatch plus its value, with an
 * optional copy-to-clipboard affordance. The state is expected to be a CSS
 * color string (hex, rgb, hsl, …).
 */
class ColorEntry extends Entry
{
    use CanBeCopyable;

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.color';
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * Color entry — renders the state as a color swatch plus its value, with an
 * optional copy-to-clipboard affordance. The state is expected to be a CSS
 * color string (hex, rgb, hsl, …).
 */
class ColorEntry extends Entry
{
    protected bool $copyable = false;

    public function copyable(bool $condition = true): static
    {
        $this->copyable = $condition;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.color';
    }
}

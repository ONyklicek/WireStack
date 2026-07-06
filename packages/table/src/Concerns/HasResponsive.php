<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireTable\Columns\Column;

/**
 * Trait HasResponsive
 *
 * Shared responsive display logic for columns. Breakpoint tokens are normalized
 * through the canonical {@see Breakpoint} enum, which owns the token → Tailwind
 * class mapping.
 *
 * @phpstan-require-extends Column
 */
trait HasResponsive
{
    protected ?string $visibleFrom = null;

    protected ?string $hiddenFrom = null;

    public function visibleFrom(string|Breakpoint $breakpoint): static
    {
        $this->visibleFrom = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    public function hiddenFrom(string|Breakpoint $breakpoint): static
    {
        $this->hiddenFrom = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    public function getResponsiveClasses(): string
    {
        $classes = [];

        if ($this->visibleFrom) {
            $classes[] = 'hidden';
            $classes[] = Breakpoint::resolve($this->visibleFrom)->tableCellClass();
        }

        if ($this->hiddenFrom) {
            $classes[] = Breakpoint::resolve($this->hiddenFrom)->hiddenAtClass();
        }

        return implode(' ', $classes);
    }

    /**
     * Check if column has responsive visibility settings.
     */
    public function hasResponsiveVisibility(): bool
    {
        return $this->visibleFrom !== null || $this->hiddenFrom !== null;
    }
}

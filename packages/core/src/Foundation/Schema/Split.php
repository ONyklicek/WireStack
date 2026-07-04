<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Canonical split layout — arranges child components side by side on a single
 * horizontal axis, stacking vertically on small screens.
 *
 * Shared schema vocabulary consumed by forms and infolists; surface-specific
 * markup lives in each package's Blade view. Children grow to share the row
 * evenly by default. The breakpoint at which the row turns horizontal is
 * configurable via {@see from()}.
 */
class Split extends LayoutComponent
{
    protected string $from = 'md';

    protected ?string $justify = null;

    protected ?string $align = null;

    protected int $gap = 4;

    protected bool $wrap = false;

    protected bool $grow = true;

    /**
     * The Tailwind breakpoint at which children lay out horizontally
     * ('sm', 'md', or 'lg'); below it they stack vertically.
     */
    public function from(string $breakpoint): static
    {
        $this->from = $breakpoint;

        return $this;
    }

    /**
     * Main-axis distribution: start|end|center|between|around|evenly.
     */
    public function justify(string $justify): static
    {
        $this->justify = $justify;

        return $this;
    }

    /**
     * Cross-axis alignment: start|end|center|stretch|baseline.
     */
    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    /**
     * Space between children (Tailwind gap scale, 0–12). Defaults to 4.
     */
    public function gap(int $gap): static
    {
        $this->gap = $gap;

        return $this;
    }

    public function wrap(bool $condition = true): static
    {
        $this->wrap = $condition;

        return $this;
    }

    /**
     * Whether children grow to fill the row evenly (default) or keep their
     * natural width.
     */
    public function grow(bool $condition = true): static
    {
        $this->grow = $condition;

        return $this;
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function isWrap(): bool
    {
        return $this->wrap;
    }

    public function isGrow(): bool
    {
        return $this->grow;
    }

    /** Row direction class scoped to the {@see from()} breakpoint. */
    public function getRowClass(): string
    {
        return match ($this->from) {
            'sm' => 'sm:flex-row',
            'lg' => 'lg:flex-row',
            default => 'md:flex-row',
        };
    }

    /** Literal gap utility for the configured spacing. */
    public function getGapClass(): string
    {
        return match (max(0, min($this->gap, 12))) {
            0 => 'gap-0', 1 => 'gap-1', 2 => 'gap-2', 3 => 'gap-3',
            5 => 'gap-5', 6 => 'gap-6', 7 => 'gap-7', 8 => 'gap-8',
            9 => 'gap-9', 10 => 'gap-10', 11 => 'gap-11', 12 => 'gap-12',
            default => 'gap-4',
        };
    }

    /** Literal justify-content utility, or '' when unset. */
    public function getJustifyClass(): string
    {
        return match ($this->justify) {
            'start' => 'justify-start',
            'end' => 'justify-end',
            'center' => 'justify-center',
            'between' => 'justify-between',
            'around' => 'justify-around',
            'evenly' => 'justify-evenly',
            default => '',
        };
    }

    /** Literal align-items utility, or '' when unset. */
    public function getAlignClass(): string
    {
        return match ($this->align) {
            'start' => 'items-start',
            'end' => 'items-end',
            'center' => 'items-center',
            'stretch' => 'items-stretch',
            'baseline' => 'items-baseline',
            default => '',
        };
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.split';
    }
}

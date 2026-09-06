<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireTable\Columns\Column;

/**
 * Trait HasResponsive
 *
 * Canonical owner of how a column behaves across viewport widths. Two separate
 * questions live here, and they compose:
 *
 *  - **Is the column there at all?** `visibleFrom()` / `hiddenFrom()` and the
 *    named shortcuts below, resolved to Tailwind classes by
 *    {@see getResponsiveClasses()}.
 *  - **Does it show different content?** `mobileDisplayUsing()` /
 *    `desktopDisplayUsing()`, separated by `mobileBreakpoint()`. {@see Column}
 *    renders that pair — it owns the cell's chrome — reading the closures here.
 *
 * Breakpoint tokens are normalized through the canonical {@see Breakpoint} enum,
 * which owns the token → Tailwind class mapping.
 *
 * **The shortcuts go through the setters, not the properties.** They used to sit
 * on `Column` and assign `$visibleFrom` / `$hiddenFrom` directly, so this trait
 * held the state while the class wrote it — two writers of one field, and
 * `onlyOnMobile()` skipped the enum normalization `hiddenFrom()` performs.
 *
 * @phpstan-require-extends Column
 */
trait HasResponsive
{
    protected ?string $visibleFrom = null;

    protected ?string $hiddenFrom = null;

    /** @var Closure|null Custom display for mobile view */
    protected ?Closure $mobileDisplayUsing = null;

    /** @var Closure|null Custom display for desktop view */
    protected ?Closure $desktopDisplayUsing = null;

    /** @var string Mobile breakpoint for display switching */
    protected string $mobileBreakpoint = 'md';

    /** Show the column only from this breakpoint up (hidden on smaller screens). */
    public function visibleFrom(string|Breakpoint $breakpoint): static
    {
        $this->visibleFrom = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    /** Hide the column from this breakpoint up (visible on smaller screens). */
    public function hiddenFrom(string|Breakpoint $breakpoint): static
    {
        $this->hiddenFrom = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    /**
     * Show column only on tablet and up (hidden below sm).
     */
    public function onlyOnTabletAndUp(): static
    {
        return $this->visibleFrom(Breakpoint::Sm);
    }

    /**
     * Show column only on large screens (hidden below lg).
     */
    public function onlyOnLargeScreens(): static
    {
        return $this->visibleFrom(Breakpoint::Lg);
    }

    /**
     * Alias for onlyOnMobile - shorter syntax.
     */
    public function mobileOnly(): static
    {
        return $this->onlyOnMobile();
    }

    /**
     * Show column only on mobile (hidden on md and up).
     */
    public function onlyOnMobile(): static
    {
        return $this->hiddenFrom(Breakpoint::Md);
    }

    /**
     * Alias for onlyOnDesktop - shorter syntax.
     */
    public function desktopOnly(): static
    {
        return $this->onlyOnDesktop();
    }

    /**
     * Show column only on desktop (hidden below md).
     */
    public function onlyOnDesktop(): static
    {
        return $this->visibleFrom(Breakpoint::Md);
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

    /**
     * Set custom display for mobile devices.
     * Use this when you want different content on mobile vs desktop.
     *
     * @param  Closure  $callback  fn($record, $column) => string
     */
    public function mobileDisplayUsing(Closure $callback): static
    {
        $this->mobileDisplayUsing = $callback;

        return $this;
    }

    /**
     * Set custom display for desktop devices.
     *
     * @param  Closure  $callback  fn($record, $column) => string
     */
    public function desktopDisplayUsing(Closure $callback): static
    {
        $this->desktopDisplayUsing = $callback;

        return $this;
    }

    /**
     * Set the breakpoint that separates mobile from desktop.
     *
     * @param  string|Breakpoint  $breakpoint  sm, md, lg, xl, 2xl
     */
    public function mobileBreakpoint(string|Breakpoint $breakpoint): static
    {
        $this->mobileBreakpoint = $breakpoint instanceof Breakpoint ? $breakpoint->value : $breakpoint;

        return $this;
    }

    /**
     * Check if column has separate mobile/desktop displays.
     */
    public function hasResponsiveDisplay(): bool
    {
        return $this->mobileDisplayUsing !== null || $this->desktopDisplayUsing !== null;
    }
}

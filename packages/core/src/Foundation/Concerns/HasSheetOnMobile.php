<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Support\MobileSheet;

/**
 * Canonical opt-in/opt-out for the "floating panel becomes a bottom sheet on
 * mobile" behaviour shared by every floating surface (action-group menus,
 * dropdowns, select/date/tag pickers, table filter & column-toggle panels).
 *
 * The per-instance setting wins; when unset it falls back to the global
 * `wire-core.mobile.sheet` config flag (default true). Consumers read the
 * resolved boolean through {@see usesSheetOnMobile()} and gate their
 * `max-sm:` sheet classes + backdrop on it.
 */
trait HasSheetOnMobile
{
    protected ?bool $sheetOnMobile = null;

    protected ?string $mobileBreakpoint = null;

    /**
     * Breakpoint below which this component presents as a sheet: 'sm' (< 640px),
     * 'md' (< 768px, incl. small tablets) or 'lg' (< 1024px, incl. tablet
     * portrait). Overrides the global `wire-core.mobile.breakpoint` for this
     * instance only.
     */
    public function mobileBreakpoint(?string $breakpoint): static
    {
        $this->mobileBreakpoint = $breakpoint;

        return $this;
    }

    /**
     * Resolved breakpoint: the per-instance value, else the global config.
     */
    public function getMobileBreakpoint(): string
    {
        return MobileSheet::breakpoint($this->mobileBreakpoint);
    }

    /**
     * Present this component's floating panel as a bottom sheet on mobile
     * (default), or pass false to keep the classic floating panel.
     */
    public function sheetOnMobile(?bool $sheet = true): static
    {
        $this->sheetOnMobile = $sheet;

        return $this;
    }

    /**
     * Resolved setting: the explicit per-instance value, else the component's
     * default ({@see defaultSheetOnMobile()}).
     */
    public function usesSheetOnMobile(): bool
    {
        return $this->sheetOnMobile ?? $this->defaultSheetOnMobile();
    }

    /**
     * Default used when no explicit value is set — the global
     * `wire-core.mobile.sheet` config flag. Components may override this (e.g.
     * searchable selects default to the classic floating dropdown on mobile).
     */
    protected function defaultSheetOnMobile(): bool
    {
        return (bool) config('wire-core.mobile.sheet', true);
    }
}

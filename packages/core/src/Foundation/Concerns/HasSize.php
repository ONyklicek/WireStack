<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Size property for components (sm, md, lg).
 */
trait HasSize
{
    protected string|Closure $size = 'md';

    public function size(string|Closure $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function sm(): static
    {
        return $this->size('sm');
    }

    public function md(): static
    {
        return $this->size('md');
    }

    public function lg(): static
    {
        return $this->size('lg');
    }

    public function getSize(): string
    {
        return $this->evaluate($this->size) ?? 'md';
    }

    /**
     * Canonical soft "pill"/badge sizing (padding + font size).
     *
     * Single source for badge-like surfaces (BadgeColumn, PollColumn, …) so the
     * xs/sm/md/lg scale stays identical everywhere. Literal class strings are
     * kept verbatim for Tailwind's JIT scanner.
     */
    public static function getBadgeSizeClasses(string $size): string
    {
        return match ($size) {
            'xs' => 'px-1.5 py-0.5 text-[10px]',
            'sm' => 'px-2 py-0.5 text-xs',
            'lg' => 'px-3 py-1 text-sm',
            'md' => 'px-2.5 py-1 text-xs',
            default => 'px-2.5 py-1 text-xs',
        };
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Column span for grid layout.
 */
trait HasColumnSpan
{
    protected int|string|null $columnSpan = null;

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function columnSpanFull(): static
    {
        $this->columnSpan = 'full';

        return $this;
    }

    public function getColumnSpan(): int|string|null
    {
        return $this->columnSpan;
    }

    /**
     * Canonical responsive grid class for the configured column span.
     *
     * Single owner of the span → Tailwind mapping so every grid surface (infolist
     * entries, layout components) stays in sync instead of re-encoding the same
     * `match` in each Blade view. `full` spans every column; 2–4 span that many
     * columns from the `sm` breakpoint up; anything else falls back to `$default`
     * — block-level surfaces (key/value, repeatable) pass `col-span-full` so an
     * unset span still fills the row.
     */
    public function getColumnSpanClass(string $default = ''): string
    {
        return match ($this->getColumnSpan()) {
            'full' => 'col-span-full',
            2 => 'sm:col-span-2',
            3 => 'sm:col-span-3',
            4 => 'sm:col-span-4',
            default => $default,
        };
    }
}

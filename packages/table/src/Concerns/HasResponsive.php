<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;

/**
 * Trait HasResponsive
 *
 * Shared responsive display logic for columns.
 *
 * @phpstan-require-extends Column
 */
trait HasResponsive
{
    protected ?string $visibleFrom = null;

    protected ?string $hiddenFrom = null;

    public function visibleFrom(string $breakpoint): static
    {
        $this->visibleFrom = $breakpoint;

        return $this;
    }

    public function hiddenFrom(string $breakpoint): static
    {
        $this->hiddenFrom = $breakpoint;

        return $this;
    }

    public function getResponsiveClasses(): string
    {
        $classes = [];

        if ($this->visibleFrom) {
            $classes[] = 'hidden';
            $classes[] = match ($this->visibleFrom) {
                'sm' => 'sm:table-cell',
                'md' => 'md:table-cell',
                'lg' => 'lg:table-cell',
                'xl' => 'xl:table-cell',
                '2xl' => '2xl:table-cell',
                default => 'md:table-cell',
            };
        }

        if ($this->hiddenFrom) {
            $classes[] = match ($this->hiddenFrom) {
                'sm' => 'sm:hidden',
                'md' => 'md:hidden',
                'lg' => 'lg:hidden',
                'xl' => 'xl:hidden',
                default => 'md:hidden',
            };
        }

        return implode(' ', $classes);
    }
}

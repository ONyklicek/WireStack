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
}

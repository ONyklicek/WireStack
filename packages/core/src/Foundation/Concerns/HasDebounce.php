<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Debounce configuration for live wire:model bindings.
 */
trait HasDebounce
{
    protected ?int $debounce = null;

    public function debounce(?int $milliseconds): static
    {
        $this->debounce = $milliseconds;

        return $this;
    }

    public function getDebounce(): ?int
    {
        return $this->debounce;
    }

    /**
     * Get the wire:model.live.debounce modifier string.
     */
    public function getDebounceModifier(): string
    {
        if ($this->debounce === null) {
            return '';
        }

        return ".debounce.{$this->debounce}ms";
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Live/reactive wire:model binding mode.
 */
trait CanBeLive
{
    protected bool $isLive = false;

    protected bool $isLiveOnBlur = false;

    /** Make the field reactive — push each change to the server on input (opt-in; default fields defer until submit). */
    public function live(bool $condition = true): static
    {
        $this->isLive = $condition;

        return $this;
    }

    /** Make the field reactive only when focus leaves it (a lighter `live()`). */
    public function liveOnBlur(bool $condition = true): static
    {
        $this->isLiveOnBlur = $condition;

        return $this;
    }

    public function isLive(): bool
    {
        return $this->isLive;
    }

    public function isLiveOnBlur(): bool
    {
        return $this->isLiveOnBlur;
    }

    /**
     * Get the wire:model modifier for Blade templates.
     */
    public function getWireModelModifier(): string
    {
        if ($this->isLive) {
            return 'live';
        }

        if ($this->isLiveOnBlur) {
            return 'blur';
        }

        return '';
    }
}

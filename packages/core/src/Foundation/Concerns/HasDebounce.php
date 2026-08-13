<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Debounce configuration for live wire:model bindings.
 */
trait HasDebounce
{
    protected ?int $debounce = null;

    /** Debounce the reactive `wire:model` by N milliseconds (pairs with `live()`). */
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
     * Get the `wire:model` debounce modifier string.
     *
     * This is the one place the modifier's shape is written. It composes onto
     * whatever `CanBeLive::getWireModelModifier()` produced, so a live field with
     * a debounce ends up as `wire:model.live.debounce.300ms` — the order Livewire
     * 4 wants, because it splits the modifier list at `live` and reads everything
     * after it as network timing.
     *
     * An explicit `debounce()` always wins. Failing that, a consumer that opts in
     * through `defaultLiveDebounce()` gets that value applied to its LIVE bindings
     * only — never to a deferred one, and never to `liveOnBlur()`, which commits on
     * an event that cannot fire fast enough to need debouncing.
     */
    public function getDebounceModifier(): string
    {
        if ($this->debounce !== null) {
            return ".debounce.{$this->debounce}ms";
        }

        $default = $this->defaultLiveDebounce();

        if ($default !== null && $this->hasLiveBinding()) {
            return ".debounce.{$default}ms";
        }

        return '';
    }

    /**
     * Debounce to apply to a live binding that set none of its own.
     *
     * Null — the default — means a binding is debounced only when it asks to be.
     * Override in a component whose live bindings should carry a floor;
     * `wire-forms`' `Field` returns 250 ms so a reactive text input does not commit
     * on every keystroke.
     */
    protected function defaultLiveDebounce(): ?int
    {
        return null;
    }

    /**
     * Whether this component is live in the `wire:model.live` sense.
     *
     * `HasDebounce` is usable without `CanBeLive` — `wire-table`'s `TextFilter`
     * takes the debounce alone — so the pairing is checked rather than required.
     */
    private function hasLiveBinding(): bool
    {
        return method_exists($this, 'isLive') && $this->isLive();
    }
}

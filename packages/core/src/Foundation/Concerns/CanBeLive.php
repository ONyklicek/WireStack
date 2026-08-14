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
     *
     * `liveOnBlur()` emits `live.blur`, not a bare `blur`. From Livewire 4 the
     * `.blur` and `.change` modifiers control when the CLIENT syncs its state,
     * not when it talks to the server, so `wire:model.blur` on its own no longer
     * sends anything — `.live` is what makes it a network binding, and `.blur`
     * then says when. The fluent API is unchanged; only the emitted attribute is.
     */
    public function getWireModelModifier(): string
    {
        if ($this->isLive) {
            return 'live';
        }

        if ($this->isLiveOnBlur) {
            return 'live.blur';
        }

        return '';
    }

    /**
     * The same binding mode, for a field that entangles instead of `wire:model`.
     *
     * `@entangle` is not `wire:model` and does not take its modifiers. Livewire's
     * Blade directive only honours `.live`, and only when handed a `WireDirective`
     * rather than a string; the JS behind it defines exactly one property on the
     * value it returns:
     *
     * ```js
     * Object.defineProperty(obj, 'live', { get() { isLive = true; return obj } })
     * ```
     *
     * So anything past `.live` is `undefined`. Appending this concern's other
     * answer — `live.blur` — produced `x-data="{ value: undefined }"` and the
     * field rendered with no state at all. (It was broken before Livewire 4 too:
     * the modifier was then a bare `blur`, and `.blur` was just as undefined.)
     *
     * **`blur` has no meaning for an entangled field.** There is no input element
     * whose focus it could follow — the value syncs through Alpine. So the
     * question collapses to whether the binding is live at all, which is what
     * `$wire.$entangle($path, $live)` takes as its second argument.
     */
    public function getEntangleModifier(): string
    {
        return $this->isLive || $this->isLiveOnBlur ? 'live' : '';
    }
}

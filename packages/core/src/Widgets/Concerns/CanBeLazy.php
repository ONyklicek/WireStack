<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use NyonCode\WireCore\Widgets\Widget;

/**
 * A widget draws a placeholder first and fetches itself afterwards.
 *
 * For the widget whose figure costs a query the rest of the page should not wait
 * for: the grid renders a skeleton card, `wire:init` calls
 * {@see WithWidgets::loadWidget()},
 * and the response carries that one widget's markup as a `wire:partial` region.
 * The page is interactive before the count comes back.
 *
 * ## Why this exists again
 *
 * `Widget::lazy()` shipped once before and was removed in 2.0 because it
 * deferred nothing: no view read the flag. The upgrade note for that removal
 * said per-widget deferral was not available at all, because it would need an
 * `@island` per widget and an island inside a `@foreach` does not compile — its
 * name is re-evaluated in its own compiled file, which never sees the loop
 * variable (`IslandSemanticsTest`).
 *
 * That was true of islands and only of islands. A partial is chosen by the
 * *server* and anchored with a plain attribute, so it has never had that
 * problem — which is why polling already answers one widget's tick with one
 * widget. Deferral is the same mechanism with a different trigger, and this
 * trait is that trigger.
 *
 * ## The rule the render standard imposes
 *
 * `AI_CODING_STANDARD.md` is explicit that eager-cheap beats lazy, and that
 * deferral is an opt-in lever for a narrow case rather than a default. It is
 * off unless asked for, and the case it is for is a *slow* widget, not a big
 * one: a widget whose markup is merely large should be made cheap instead.
 *
 * ## Once loaded, loaded
 *
 * Laziness is a first-render decision. The host records which widgets have been
 * fetched and clears the flag on them before anything renders, so a later poll
 * tick, filter change or full render draws the real widget rather than dropping
 * back to the skeleton.
 *
 * @see Widget::lazy()
 */
trait CanBeLazy
{
    protected bool $lazy = false;

    /**
     * Defer this widget's first render: a placeholder now, the widget itself on
     * the round trip that follows.
     */
    public function lazy(bool $condition = true): static
    {
        $this->lazy = $condition;

        return $this;
    }

    public function isLazy(): bool
    {
        return $this->lazy;
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

/**
 * What a widget shows when it has nothing to show.
 *
 * On the base rather than on {@see HasWidgetItems}, where it started. It was
 * put there because the widgets that draw a series were the first to need it,
 * and it stayed there until a widget with no `items()` at all needed the same
 * sentence — `TableWidget`, whose query can come back empty exactly as a list's
 * can. "A card with nothing in it says so" is not a property of having a series;
 * it is a property of being a widget.
 */
trait HasEmptyState
{
    protected ?string $emptyState = null;

    /** The message shown when there is nothing to draw; null restores the default. */
    public function emptyState(?string $message): static
    {
        $this->emptyState = $message;

        return $this;
    }

    public function getEmptyState(): string
    {
        return $this->emptyState ?? (string) __('wire-core::messages.widget_empty');
    }
}

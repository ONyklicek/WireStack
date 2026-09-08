<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a dashboard offers to remember a layout it cannot address.
 */
final class WidgetLayoutException extends RuntimeException implements WireException
{
    /**
     * A customisable dashboard has a widget with no key of its own.
     *
     * Keys are derived from a widget's position when none is set — `w0`, `w1` —
     * which is fine for a dashboard nobody rearranges and quietly wrong for one
     * that does. A stored layout addresses widgets by key, so the first time
     * somebody inserts a widget at the top of the declaration, every saved
     * layout silently starts describing different widgets: the user's revenue
     * card is now their churn card, at the size they chose for revenue.
     *
     * Loud at the moment the dashboard renders, rather than a note in the docs,
     * because the failure it prevents is invisible — the page still renders, and
     * it renders the wrong thing.
     */
    public static function widgetHasNoKey(string $layoutKey, int $index, string $widget): self
    {
        return new self(sprintf(
            'The dashboard [%s] remembers a layout per user, so every widget on it needs a key that '
            .'survives the declaration changing. Widget #%d (%s) has none — give it one with '
            .'->key(\'…\'), or drop the customisable() opt-in.',
            $layoutKey,
            $index,
            class_basename($widget),
        ));
    }
}

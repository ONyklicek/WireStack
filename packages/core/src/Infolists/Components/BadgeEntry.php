<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * Badge entry — a {@see TextEntry} that renders as a colored pill by default.
 *
 * First-class ergonomic alias for `TextEntry::make(...)->badge()`. Inherits the
 * canonical color/icon/format vocabulary and reuses the text entry view, so the
 * badge chrome stays owned in one place ({@see TextEntry} + text entry Blade).
 */
class BadgeEntry extends TextEntry
{
    protected bool $badge = true;
}

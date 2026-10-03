<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Http\Request;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireTable\Concerns\StacksOnMobile;

/**
 * How wide the browser that sent this request is, as far as the cookie says.
 *
 * A table stacked on mobile is two renderings of every record — the `<table>`
 * and the cards — of which CSS shows one. The browser knows which; the server
 * does not, so `wire-table-viewport.js` writes the largest breakpoint the window
 * currently reaches into the `wire_viewport` cookie (`base`, `sm` … `2xl`), and
 * every later request — a page load as much as a Livewire update — carries it.
 * {@see StacksOnMobile::getClientLayout()} turns
 * that into "emit only the table" or "emit only the cards".
 *
 * **A missing or unreadable cookie is `null`, never a guess.** The first visit,
 * a bot, a test and a browser with cookies off all have none, and for them the
 * table renders both halves exactly as it always did and lets CSS choose. The
 * value is written by the browser and only ever trims markup the browser would
 * hide — it decides nothing about what anybody may see.
 *
 * The ladder is matched by `matchMedia()` against the same `rem` widths the
 * utilities use ({@see Breakpoint::minWidth()}), not by pixels, so the cookie and
 * the CSS cannot disagree about which side of a breakpoint the window is on.
 */
final class ClientViewport
{
    /** Excluded from cookie encryption by the service provider: the browser writes it. */
    public const COOKIE = 'wire_viewport';

    /** The value below the smallest breakpoint. */
    public const BASE = 'base';

    /**
     * Whether the window reaches the breakpoint: `true` at or above it, `false`
     * below, `null` when the request does not say.
     */
    public static function reaches(Breakpoint $breakpoint, ?Request $request = null): ?bool
    {
        $current = self::rank(($request ?? request())->cookie(self::COOKIE));

        return $current === null ? null : $current >= self::rank($breakpoint->value);
    }

    /**
     * Position on the ladder — `base` 0, `sm` 1 … `2xl` 5 — or null for anything
     * that is not a breakpoint name (an absent cookie, a forged one, an array).
     */
    private static function rank(mixed $value): ?int
    {
        if ($value === self::BASE) {
            return 0;
        }

        if (! is_string($value) || ($breakpoint = Breakpoint::tryFrom($value)) === null) {
            return null;
        }

        return array_search($breakpoint, Breakpoint::cases(), true) + 1;
    }
}

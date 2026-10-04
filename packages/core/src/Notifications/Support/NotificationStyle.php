<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Support;

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;
use NyonCode\WireCore\Foundation\Colors\NoticePalette;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * What a notification type looks like on a server-rendered surface.
 *
 * The toast container already answers this, in Alpine, because a toast is
 * created in the browser from a payload — and that is the right owner for *that*
 * surface. The bell's panel is rendered in PHP, from rows, so it needs the same
 * semantics resolved on this side. This is that resolver, and it is one class
 * rather than a `match` inside the bell so the next server-rendered notification
 * surface extends it instead of writing a third copy.
 *
 * The colour is a role from {@see HasColor}'s vocabulary
 * (`success` / `danger` / `warning` / `info`), not a hue, so a re-themed palette
 * follows. The tint classes are here rather than borrowed from `HasColor`
 * because none of its five resolvers is this surface: they are button surfaces,
 * carrying `hover:` and `focus:ring-` states a list row's icon must not have.
 * Distinct surface, distinct rendering rule — shared semantics.
 *
 * Class strings stay literal so Tailwind's scanner sees every one of them, and
 * so the match arms double as an allow-list: `$type` reaches this straight out
 * of a stored `data` payload.
 */
final class NotificationStyle
{
    private function __construct(
        public readonly string $type,
        public readonly string $color,
        public readonly string $icon,
        public readonly string $tint,
    ) {}

    /**
     * The style for a notification type. Anything unrecognised reads as `info`,
     * which is what a payload written by an older version of an application, or
     * by hand, will land on.
     */
    public static function for(?string $type): self
    {
        // The tint is the role's, through the canonical icon ink — so a role
        // pointed elsewhere in `wire-core.colors` repaints the bell's icons too.
        return match ($type) {
            'success' => new self('success', 'success', 'outline:check-circle', NoticePalette::iconText('success')),
            'error' => new self('error', 'danger', 'outline:x-circle', NoticePalette::iconText('danger')),
            'warning' => new self('warning', 'warning', 'outline:exclamation-triangle', NoticePalette::iconText('warning')),
            default => new self('info', 'info', 'outline:information-circle', NoticePalette::iconText('info')),
        };
    }

    /**
     * The pill classes for one action button on this notification.
     *
     * The vocabulary is the toast container's — `success`, `error`, `warning`,
     * `info`, `primary`, `gray` — and it is the same six on purpose: an action
     * written once must not mean one thing in a toast and something else in the
     * panel three days later. The *rendering* differs, because these are two
     * surfaces: a toast's action is a text link on a coloured card, a panel's is
     * a pill on a white row.
     *
     * Falls back to the notification's own colour the way the toast does, so an
     * action that named none reads as the message it belongs to rather than as
     * the page's primary call to action.
     */
    public function actionClasses(?string $color): string
    {
        // Taller below `sm`: at `py-1` the button is 24 pixels, which is a
        // comfortable target for a cursor and a miss for a thumb. The panel and
        // the toast both draw it, and both are read on a phone.
        $base = 'inline-flex items-center gap-1 rounded-md px-2 py-2 text-xs font-medium transition sm:py-1 ';

        // Only the six named here are this surface's vocabulary; anything else is
        // the gray pill, as it always was. The hue behind a role is the
        // canonical palette's, so `wire-core.colors` repaints these with the rest.
        $color = match ($color ?? $this->color) {
            'success', 'warning', 'info', 'primary' => $color ?? $this->color,
            'danger', 'error' => 'danger',
            default => 'gray',
        };

        return $base.ButtonPalette::soft($color);
    }

    /**
     * The icon to draw, letting a notification that named its own win.
     *
     * A notification carries an optional icon and that is the author's choice
     * about this one message; the type's icon is the fallback for the many that
     * say nothing.
     */
    public function iconOr(?string $icon): string
    {
        return $icon !== null && $icon !== '' ? $icon : $this->icon;
    }
}

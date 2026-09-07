<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Notifications\Support;

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
        return match ($type) {
            'success' => new self('success', 'success', 'outline:check-circle', 'text-emerald-500 dark:text-emerald-400'),
            'error' => new self('error', 'danger', 'outline:x-circle', 'text-red-500 dark:text-red-400'),
            'warning' => new self('warning', 'warning', 'outline:exclamation-triangle', 'text-amber-500 dark:text-amber-400'),
            default => new self('info', 'info', 'outline:information-circle', 'text-cyan-500 dark:text-cyan-400'),
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
        $base = 'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition ';

        return $base.match ($color ?? $this->color) {
            'success' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50',
            'danger', 'error' => 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50',
            'warning' => 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50',
            'info' => 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300 dark:hover:bg-cyan-900/50',
            'primary' => 'bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50',
            default => 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        };
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

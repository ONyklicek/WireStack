<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Contracts;

/**
 * A settings group only some people may see.
 *
 * `wire-module-settings.permission` guards the screen as a whole — one ability
 * on the route, the way the audit module guards its log. This is the finer cut:
 * mail settings an operator may change beside billing settings only an owner
 * may, on one screen.
 *
 *   public static function permission(): ?string { return 'settings.billing'; }
 *
 * Checked with `Gate::allows()` and nothing else, so Laravel's own gates,
 * `spatie/laravel-permission` and `nyoncode/laravel-permission-extended` — its
 * wildcards and super-admin included — all answer it the way they answer every
 * other check in this framework. A group the current user fails is not in the
 * switcher, and asking for its URL directly is a 403 rather than a blank form
 * that silently writes nothing.
 */
interface GuardsSettingsGroup
{
    /** The ability required to see and save this group, or null for none. */
    public static function permission(): ?string;
}

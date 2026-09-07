<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Resources;

use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireModuleSettings\Contracts\GuardsSettingsGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Support\SettingsGroups;
use Throwable;

/**
 * Settings, as something the catalogue can hold.
 *
 * A resource with **no model**, which the contract has always allowed: what
 * `DescribesResource` really carries is identity — a key, a label, a place in a
 * menu — and settings have all three while having no table of records. That is
 * what lets one page be routed, listed and found by the same machinery every
 * other screen uses, without a second registration path for "pages that are not
 * resources".
 *
 * ## What guards it
 *
 * `wire-module-settings.permission` becomes `can:` middleware on both routes,
 * and hides the menu entry that leads to them — one line, the same shape the
 * audit module uses, and nothing here re-implements an authorization check.
 * Null by default, because a permission this package invented would lock the
 * screen out of every installation that has no such ability. A settings screen
 * is, after the audit log, the one most worth naming one for: it is where an
 * application's behaviour is changed without a deploy.
 *
 * A group may name an ability of its own on top of that
 * ({@see GuardsSettingsGroup}) — mail
 * settings an operator may change beside billing settings only an owner may, on
 * one screen.
 */
class SettingsResource implements DescribesResource, ProvidesNavigation, ProvidesPages
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'settings';
    }

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function label(): string
    {
        return __('wire-module-settings::messages.settings');
    }

    public static function pluralLabel(): string
    {
        return __('wire-module-settings::messages.settings');
    }

    public static function pages(): array
    {
        return [
            'index' => self::guard(SettingsPage::class),
            // A group is a URL, so a person can bookmark "mail settings" and land
            // on it. `{record}` is the page's mount argument, which for this page
            // is the group name rather than a key.
            'view' => self::guard(SettingsPage::class),
        ];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-settings::messages.settings'))
            ->group((string) config('wire-module-settings.navigation.group', 'system'))
            ->icon((string) config('wire-module-settings.navigation.icon', 'outline:cog-6-tooth'))
            // Hidden rather than shown and then refused. A menu entry whose route
            // answers 403 is worse than no entry: it is the panel telling someone
            // a screen exists for them and then telling them it does not.
            ->visible(static fn (): bool => self::authorized());
    }

    /** Where one group's screen lives, or null when nothing routes it. */
    public static function urlForGroup(string $group): ?string
    {
        return app(ResolvesPageUrls::class)->urlFor(self::key(), 'view', ['record' => $group], Zone::current());
    }

    /** The ability guarding the screen, or null when the application named none. */
    public static function permission(): ?string
    {
        $permission = config('wire-module-settings.permission');

        return is_string($permission) && $permission !== '' ? $permission : null;
    }

    /**
     * Whether the current user may reach the screen at all.
     *
     * The screen's own ability, and then whether anything on it is theirs: a
     * panel where every declared group is guarded and this user passes none has
     * nothing to show them, and an entry leading to an empty screen is the same
     * broken promise as one leading to a 403.
     *
     * An application that has declared *nothing* is the exception, and stays
     * visible on purpose. That screen is the one carrying the instructions for
     * declaring a group, and hiding it would mean the module answers a fresh
     * installation by removing the only page that explains itself.
     */
    public static function authorized(): bool
    {
        $permission = self::permission();

        try {
            if ($permission !== null && ! Gate::allows($permission)) {
                return false;
            }
        } catch (Throwable) {
            // Fail closed — a context with no resolvable guard authorizes nothing.
            return false;
        }

        return SettingsGroups::all() === [] || SettingsGroups::visible() !== [];
    }

    /**
     * A page as declared: the bare component, or one behind the configured
     * ability.
     *
     * @param  class-string  $component
     * @return class-string|RoutePage
     */
    private static function guard(string $component): string|RoutePage
    {
        $permission = self::permission();

        return $permission === null
            ? $component
            : RoutePage::make($component)->permission($permission);
    }
}

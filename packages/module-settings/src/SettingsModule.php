<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleSettings\Pages\SettingsPage;
use NyonCode\WireModuleSettings\Resources\SettingsResource;

/** What this package contributes: a place to keep what an application configures. */
class SettingsModule extends Module
{
    public function getId(): string
    {
        return 'settings';
    }

    /**
     * The screen, unless the application switched it off.
     *
     * Off is storage only: `Settings`, the groups and their contracts all
     * work, and nothing is registered under the `settings` key — the key an
     * application that has its own settings section is already using, and which
     * the registry refuses twice. That application routes {@see SettingsPage}
     * (or a subclass) itself.
     */
    public function resources(): array
    {
        return self::screen() ? [SettingsResource::class] : [];
    }

    public function navigation(): ?NavigationGroup
    {
        // No screen, no heading to put it under: a group nothing sits in would
        // be an empty heading in the menu.
        if (! self::screen()) {
            return null;
        }

        $group = NavigationGroup::make((string) config('wire-module-settings.navigation.group', 'system'))
            ->icon('outline:wrench-screwdriver')
            ->sort((int) config('wire-module-settings.navigation.sort', 97));

        $label = config('wire-module-settings.navigation.label');

        // A closure, and that is not style: `navigation()` is called while
        // core spreads modules into the registries, which is **before** this
        // package's own provider has registered its translations. A `__()`
        // evaluated there misses, and the translator caches the miss for the
        // whole request — so every later lookup in this namespace answers
        // with the key. Resolved at render, it is simply right.
        return is_string($label) && $label !== ''
            ? $group->label($label)
            : $group->label(fn (): string => __('wire-module-settings::messages.system'));
    }

    /** Whether the module registers its own screen (`wire-module-settings.screen`). */
    public static function screen(): bool
    {
        return (bool) config('wire-module-settings.screen', true);
    }
}

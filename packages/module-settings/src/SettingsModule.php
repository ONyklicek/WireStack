<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleSettings\Resources\SettingsResource;

/** What this package contributes: a place to keep what an application configures. */
class SettingsModule extends Module
{
    public function getId(): string
    {
        return 'settings';
    }

    public function resources(): array
    {
        return [SettingsResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
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
}

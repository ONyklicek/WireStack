<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Roles;

/**
 * What this package contributes to an application: the users area.
 *
 * A manifest, as every module is — it names classes and a menu heading, and the
 * registries that own resources and navigation do the owning. The role resource
 * appears only where roles exist, which is the one decision this class makes.
 */
class UsersModule extends Module
{
    public function getId(): string
    {
        return 'users';
    }

    public function resources(): array
    {
        $resources = [UserResource::class];

        if (Roles::enabled()) {
            $resources[] = RoleResource::class;
        }

        return $resources;
    }

    public function navigation(): ?NavigationGroup
    {
        $group = NavigationGroup::make((string) config('wire-module-users.navigation.group', 'access'))
            ->icon((string) config('wire-module-users.navigation.icon', 'outline:users'))
            ->sort((int) config('wire-module-users.navigation.sort', 90));

        $label = config('wire-module-users.navigation.label');

        // A closure, and that is not style: `navigation()` is called while
        // core spreads modules into the registries, which is **before** this
        // package's own provider has registered its translations. A `__()`
        // evaluated there misses, and the translator caches the miss for the
        // whole request — so every later lookup in this namespace answers
        // with the key. Resolved at render, it is simply right.
        return is_string($label) && $label !== ''
            ? $group->label($label)
            : $group->label(fn (): string => __('wire-module-users::messages.access'));
    }
}

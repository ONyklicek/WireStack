<?php

declare(strict_types=1);

namespace NyonCode\WireModuleNotifications;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleNotifications\Resources\NotificationResource;

/** What this package contributes: the notification history. */
class NotificationsModule extends Module
{
    public function getId(): string
    {
        return 'notifications';
    }

    public function resources(): array
    {
        return [NotificationResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        $group = NavigationGroup::make((string) config('wire-module-notifications.navigation.group', 'system'))
            ->icon('outline:wrench-screwdriver')
            ->sort((int) config('wire-module-notifications.navigation.sort', 96));

        $label = config('wire-module-notifications.navigation.label');

        // A closure, and that is not style: `navigation()` is called while
        // core spreads modules into the registries, which is **before** this
        // package's own provider has registered its translations. A `__()`
        // evaluated there misses, and the translator caches the miss for the
        // whole request — so every later lookup in this namespace answers
        // with the key. Resolved at render, it is simply right.
        return is_string($label) && $label !== ''
            ? $group->label($label)
            : $group->label(fn (): string => __('wire-module-notifications::messages.system'));
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'navigation.building' hook.
 *
 * Dispatched on the flat, keyed list of entries — after visibility and the
 * label/URL fallbacks, before grouping and sorting. One site rather than two:
 * `Workspace::navigation()` and `items()` both read this list, and a hook that
 * fired in only one of them would make the grouped menu and the flat menu
 * disagree about what is in the menu.
 *
 * The array is keyed by the key each class was registered under, and the keys
 * matter: they are how a consumer turns an entry into a link. Preserve them.
 *
 * ```php
 * unset($payload->items['media']);                       // hide a module's row
 * $payload->items['docs'] = NavigationItem::make('Docs')->url('/docs')->sort(90);
 * ```
 *
 * Sorting here is wasted work — `sort()` on the item is what orders the menu,
 * and the grouping step re-sorts whatever this leaves.
 *
 * Scoped by **zone**: `for: 'admin'` narrows a callback to the menu drawn in that
 * zone. A menu built for no zone carries no scope and a scoped callback sits it
 * out, by the rule every dispatch without a target follows.
 *
 * Typed only.
 */
final class NavigationBuildingPayload implements HasHookTarget
{
    /**
     * @param  array<string, mixed>  $items  Entries keyed by registered key (modifiable)
     * @param  string|null  $zone  The zone this menu is being built for, when there is one
     * @param  HookTarget|null  $target  Which zone this came from, for scoped callbacks
     */
    public function __construct(
        public array $items,
        public readonly ?string $zone = null,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'zone' => $this->zone,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}

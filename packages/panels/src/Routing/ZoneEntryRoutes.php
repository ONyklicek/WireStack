<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;

/**
 * The `zones` group: the address above the zones, `wire.zones`, which sends a
 * person into the zone they belong in (ADR 0027). Option `uri`, `/` by default.
 */
final class ZoneEntryRoutes implements ProvidesRoutes
{
    public static function key(): string
    {
        return 'zones';
    }

    public function defaults(): array
    {
        return ['middleware' => ['web', 'auth']];
    }

    public function fixesNames(): bool
    {
        // `fortify.home` and a zone switcher's "all zones" link point at it.
        return true;
    }

    public function register(array $options): array
    {
        return ['entry' => ResourceRoutes::zoneEntry((string) ($options['uri'] ?? '/'))];
    }
}

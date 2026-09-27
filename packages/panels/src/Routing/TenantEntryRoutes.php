<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;

/**
 * The `tenant-entry` group: a hand-written tenant zone's bare address (ADR 0040).
 *
 * `Route::wire('panel', tenant: 'path')` registers it by itself; this is for the
 * zone written out as `prefix('app/{tenant}')` around `Route::wire('panel')`,
 * which has to say where a company's address is: option `to` —
 * `app/{tenant}`, or `//{tenant}.example.com` — and `uri`, where the bare
 * address answers relative to the group.
 */
final class TenantEntryRoutes implements ProvidesRoutes
{
    public static function key(): string
    {
        return 'tenant-entry';
    }

    public function defaults(): array
    {
        return ['middleware' => ['web', 'auth']];
    }

    public function fixesNames(): bool
    {
        return false;
    }

    public function register(array $options): array
    {
        return ['entry' => ResourceRoutes::tenantEntry((string) ($options['uri'] ?? ''), (string) ($options['to'] ?? ''))];
    }
}

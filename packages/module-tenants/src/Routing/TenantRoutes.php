<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;
use NyonCode\WireModuleTenants\Http\Controllers\AcceptInvitationController;
use NyonCode\WireModuleTenants\Livewire\RegisterTenant;

/**
 * The `tenants` group: the two things that happen outside any company —
 * registering one, and accepting an invitation into one (ADR 0040 §8).
 *
 * Placed by the application like every group (ADR 0041): `Route::wire('tenants')`
 * inside a group of its own in routes/web.php, or a `tenants` entry of
 * `wire-core.routes.groups`. The prefix, the domain and the middleware — `can:`
 * included — are that group's; the routes come back keyed, `register` and
 * `accept`, for anything one of them needs alone. The names are fixed: the
 * invitation e-mail links to `wire-module-tenants.invitations.accept`.
 * Everything else of the module is a page of the tenant zone.
 */
final class TenantRoutes implements ProvidesRoutes
{
    public const REGISTER = 'wire-module-tenants.register';

    public const ACCEPT = 'wire-module-tenants.invitations.accept';

    public static function key(): string
    {
        return 'tenants';
    }

    public function defaults(): array
    {
        // The prefix the reserved slugs keep free, and a signed-in person — the
        // only one either screen is for.
        return ['prefix' => 'tenants', 'middleware' => ['web', 'auth']];
    }

    public function fixesNames(): bool
    {
        return true;
    }

    /** @return array{register: Route, accept: Route} */
    public function register(array $options): array
    {
        return [
            'register' => RouteFacade::get('register', RegisterTenant::class)->name(self::REGISTER),
            'accept' => RouteFacade::get('invitations/{invitation}', AcceptInvitationController::class)
                ->middleware('signed')
                ->name(self::ACCEPT),
        ];
    }
}

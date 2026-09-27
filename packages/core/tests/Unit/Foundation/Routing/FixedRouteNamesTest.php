<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;
use NyonCode\WireCore\Foundation\Routing\FixedRouteNames;

/*
 * A macro whose routes are linked to by a fixed name takes the application's
 * prefix and middleware, and refuses its name prefix — the one part of a group
 * that would rename what an e-mail links to.
 */

it('lets a macro be called at the top of the file, or in a group with a prefix and middleware', function () {
    FixedRouteNames::refuseNamedGroup('wireTenants');

    Route::middleware(['web'])->prefix('tenants')->group(function (): void {
        FixedRouteNames::refuseNamedGroup('wireTenants');
    });

    expect(true)->toBeTrue();
});

it('refuses a group that names its routes', function () {
    Route::name('app.')->group(function (): void {
        FixedRouteNames::refuseNamedGroup('wireTenants');
    });
})->throws(RouteRegistrationException::class, 'Route::wireTenants() was called inside a group named [app.]');

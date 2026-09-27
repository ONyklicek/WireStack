<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireModuleTenants\Http\Controllers\AcceptInvitationController;
use NyonCode\WireModuleTenants\Livewire\RegisterTenant;

/*
 * The two things that happen outside any company: registering one, and
 * accepting an invitation into one. Everything else of the module is a page
 * of the tenant zone, routed by the application's `Route::wireResources()`.
 */
Route::middleware((array) config('wire-module-tenants.routes.middleware', ['web', 'auth']))
    ->prefix((string) config('wire-module-tenants.routes.prefix', 'tenants'))
    ->name('wire-module-tenants.')
    ->group(function (): void {
        Route::get('register', RegisterTenant::class)->name('register');

        Route::get('invitations/{invitation}', AcceptInvitationController::class)
            ->middleware('signed')
            ->name('invitations.accept');
    });

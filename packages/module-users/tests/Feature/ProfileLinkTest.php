<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\ProfileLink;
use NyonCode\WireModuleUsers\Tests\Fixtures\AvatarUser;

/*
 * The link to your own account, from wherever you are.
 *
 * Found on an application with zones: the menu asked for the profile page
 * without a zone, there is no unzoned `wire.users.profile` in such an
 * application, and the link was gone from every menu — while the sign-out next
 * to it was everywhere. A person could leave, and could not change their
 * password.
 */

beforeEach(function () {
    config()->set('wire-module-users.model', AvatarUser::class);
    config()->set('auth.providers.users.model', AvatarUser::class);
    config()->set('wire-module-users.roles', false);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('avatar_path')->nullable();
        $table->timestamps();
    });

    $this->actingAs(AvatarUser::query()->create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'x']));

    Gate::define('zone.sales', fn (): bool => true);
    Gate::define('zone.admin', fn (): bool => false);
});

/**
 * A zone as an application builds one: a named group behind its own
 * permission, with the account page in it and a probe that asks from inside.
 */
function plZone(string $zone, bool $withProfile = true): void
{
    Route::middleware(['web', 'can:zone.'.$zone])->name($zone.'.')->prefix($zone)->group(function () use ($withProfile): void {
        // Named the way a page of the zone is, so the zone is readable off it.
        Route::get('probe', fn (): string => app(ProfileLink::class)->url(auth()->user()) ?? 'none')->name('wire.probe.index');

        if ($withProfile) {
            Route::wireResource(UserResource::class, pages: ['profile']);
        }
    });

    Route::getRoutes()->refreshNameLookups();
}

it('links to the account page in the zone the person is in', function () {
    plZone('sales');
    plZone('admin');

    $this->get('/sales/probe')->assertSee(url('/sales/users/profile'), false);
});

it('routes only the account page when asked for it, not the user management beside it', function () {
    plZone('sales');

    expect(Route::has('sales.wire.users.profile'))->toBeTrue()
        ->and(Route::has('sales.wire.users.index'))->toBeFalse()
        ->and(Route::has('sales.wire.users.edit'))->toBeFalse();
});

it('finds the page outside any zone when the current one does not route it', function () {
    plZone('sales', withProfile: false);
    Route::middleware('web')->group(fn () => Route::wireResource(UserResource::class, pages: ['profile']));
    Route::getRoutes()->refreshNameLookups();

    $this->get('/sales/probe')->assertSee(url('/users/profile'), false);
});

it('reaches into another zone only if that zone would let the person in', function () {
    // The account page lives only in the administrators' zone. Linking there
    // would be a link into a 403 for everybody else.
    plZone('sales', withProfile: false);
    plZone('admin');

    $this->get('/sales/probe')->assertSee('none');

    Gate::define('zone.admin', fn (): bool => true);

    $this->get('/sales/probe')->assertSee(url('/admin/users/profile'), false);
});

it('draws the menu item with that link, and nothing where there is no page to go to', function () {
    Route::middleware('web')->group(fn () => Route::wireResource(UserResource::class, pages: ['profile']));
    Route::getRoutes()->refreshNameLookups();

    expect(Blade::render("@include('wire-module-users::profile-menu-item')"))
        ->toContain('data-testid="admin-profile-link"')
        ->toContain(url('/users/profile'));
});

it('draws nothing when the application routes no account page at all', function () {
    expect(trim(Blade::render("@include('wire-module-users::profile-menu-item')")))->toBe('');
});

it('draws the menu item inside a zone, where it used to be missing', function () {
    // The regression itself: rendered on a zoned page, the old item asked for
    // an unzoned page that does not exist and drew nothing.
    plZone('sales');
    Route::middleware('web')->name('sales.')->prefix('sales')
        ->get('menu', fn (): string => Blade::render("@include('wire-module-users::profile-menu-item')"))
        ->name('wire.menu.index');
    Route::getRoutes()->refreshNameLookups();

    $this->get('/sales/menu')
        ->assertSee('data-testid="admin-profile-link"', false)
        ->assertSee(url('/sales/users/profile'), false);
});

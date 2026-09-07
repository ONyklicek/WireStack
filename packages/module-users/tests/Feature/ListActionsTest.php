<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Pages\ListRoles;
use NyonCode\WireModuleUsers\Pages\ListUsers;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use Spatie\Permission\Models\Role;

/*
 * The way out of a list.
 *
 * Both lists shipped with no row action at all: the edit pages were routed and
 * unreachable from the screen in front of them. What is asserted here is not
 * that buttons exist — it is the two rules that decide whether one should.
 *
 * **A link is a page's answer, not a resource's.** Where a record's pages live
 * depends on the zone the list was opened in, and a resource mounted twice has
 * two sets of URLs. So the actions are declared on the page, from the zone it
 * read in `mount()`.
 *
 * **No page, no button.** An application may route the list and nothing else.
 */

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });
});

/** The module's pages, routed the way `Route::wireResources()` routes them. */
function routeUsersAndRoles(): void
{
    Route::middleware('web')->group(function (): void {
        Route::wireResources();
    });
}

it('links every row to the pages this application routes', function () {
    routeUsersAndRoles();

    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]);
    $other = User::query()->create(['name' => 'Mason', 'email' => 'm@example.com', 'password' => Hash::make('x')]);

    $this->be($me);

    $html = Livewire::test(ListUsers::class)->html();

    expect($html)->toContain('data-testid="action-view"')
        ->and($html)->toContain('data-testid="action-edit"')
        ->and($html)->toContain('users/'.$other->getKey().'/edit')
        ->and($html)->toContain('data-testid="header-action-create"')
        ->and($html)->toContain('users/create');
});

it('offers no way to delete the person reading the page', function () {
    // Not politeness: an administrator who removes their own row is signed out
    // mid-request into an application they can no longer reach, and if they were
    // the only one, nobody can. Closing your own account is the profile page's
    // business, where it asks twice and takes a password.
    routeUsersAndRoles();

    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]);
    User::query()->create(['name' => 'Mason', 'email' => 'm@example.com', 'password' => Hash::make('x')]);

    $this->be($me);

    $html = Livewire::test(ListUsers::class)->html();

    // Two rows, one delete button.
    expect(substr_count($html, 'data-testid="action-delete"'))->toBe(1);
});

it('deletes the row it was pressed on, and no other', function () {
    routeUsersAndRoles();

    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]);
    $doomed = User::query()->create(['name' => 'Mason', 'email' => 'm@example.com', 'password' => Hash::make('x')]);

    $this->be($me);

    // `confirmed: true` because the preset carries a confirmation modal; what
    // is under test is what the button does once it is confirmed.
    Livewire::test(ListUsers::class)
        ->call('executeTableAction', (string) $doomed->getKey(), 'delete', true)
        ->assertHasNoErrors();

    expect(User::query()->find($doomed->getKey()))->toBeNull()
        ->and(User::query()->find($me->getKey()))->not->toBeNull();
});

it('draws no buttons at all where nothing is routed', function () {
    // An application may mount the list and nothing else. The honest answer to
    // "there is no edit page" is no Edit — not a button that leads nowhere.
    $me = User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]);

    $this->be($me);

    $html = Livewire::test(ListUsers::class)->html();

    expect($html)->not->toContain('data-testid="action-view"')
        ->and($html)->not->toContain('data-testid="action-edit"')
        ->and($html)->not->toContain('data-testid="header-action-create"');
});

it('gives roles all three actions, view included', function () {
    // RoleResource declares a `view` page: a role's permission list outgrows the
    // multi-select that edits it, so there is something to read that the form
    // cannot lay out.
    routeUsersAndRoles();

    Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);

    $this->be(User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]));

    $html = Livewire::test(ListRoles::class)->html();

    expect($html)->toContain('data-testid="action-view"')
        ->and($html)->toContain('data-testid="action-edit"')
        ->and($html)->toContain('data-testid="action-delete"')
        ->and($html)->toContain('roles/create');
});

it('deletes a role', function () {
    routeUsersAndRoles();

    $role = Role::query()->create(['name' => 'editor', 'guard_name' => 'web']);

    $this->be(User::query()->create(['name' => 'Amelia', 'email' => 'a@example.com', 'password' => Hash::make('x')]));

    Livewire::test(ListRoles::class)->call('executeTableAction', (string) $role->getKey(), 'delete', true);

    expect(Role::query()->find($role->getKey()))->toBeNull();
});

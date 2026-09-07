<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireModuleUsers\Resources\RoleResource;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\UsersModule;

/*
 * A module that arrives as a package — the path ADR 0029 describes, with a real
 * consumer at last. An application installs this and edits nothing: no config
 * entry, no class list, no provider of its own.
 */

it('registers itself, without the application listing it anywhere', function () {
    expect(app(PluginManager::class)->has('users'))->toBeTrue()
        ->and(app(PluginManager::class)->get('users'))->toBeInstanceOf(UsersModule::class)
        ->and(new UsersModule)->toBeInstanceOf(Module::class);
});

it('declares the users resource, and roles only where they exist', function () {
    expect((new UsersModule)->resources())->toContain(UserResource::class);

    config()->set('wire-module-users.roles', false);

    expect((new UsersModule)->resources())->not->toContain(RoleResource::class);

    config()->set('wire-module-users.roles', true);

    expect((new UsersModule)->resources())->toContain(RoleResource::class);
});

it('finds roles in this application, because the model carries them', function () {
    // Both halves are checked, and the second is the one that bites: the package
    // can be installed while the application's own User never took the trait, in
    // which case every role save would fail at the last step.
    expect(Roles::available())->toBeTrue()
        ->and(Roles::enabled())->toBeTrue()
        ->and(Roles::hasExtendedPermissions())->toBeTrue();
});

it('is off when the user model cannot carry roles', function () {
    config()->set('wire-module-users.model', 'Illuminate\\Foundation\\Auth\\User');

    expect(Roles::available())->toBeFalse()
        ->and(Roles::enabled())->toBeFalse();
});

it('lets configuration answer instead of looking', function () {
    config()->set('wire-module-users.roles', false);
    expect(Roles::enabled())->toBeFalse();

    config()->set('wire-module-users.roles', true);
    expect(Roles::enabled())->toBeTrue();
});

it('reads the role model from the permission package rather than hardcoding it', function () {
    expect(Roles::roleModel())->toBe(config('permission.models.role'))
        ->and(Roles::permissionModel())->toBe(config('permission.models.permission'));

    config()->set('permission.models.role', 'App\\Models\\Role');

    expect(Roles::roleModel())->toBe('App\\Models\\Role');
});

it('puts both resources under one menu group', function () {
    expect((new UsersModule)->navigation()?->getKey())->toBe('access')
        ->and(UserResource::navigation()->getGroup())->toBe('access')
        ->and(RoleResource::navigation()->getGroup())->toBe('access');
});

it('names its pages, its labels and its menu entries', function () {
    expect(UserResource::pages())->toHaveKeys(['index', 'create', 'view', 'edit'])
        ->and(UserResource::label())->not->toBe('')
        ->and(UserResource::pluralLabel())->not->toBe('')
        ->and(RoleResource::pages())->toHaveKeys(['index', 'create', 'edit'])
        ->and(RoleResource::label())->not->toBe('')
        ->and(RoleResource::pluralLabel())->not->toBe('');
});

it('takes the user model from configuration, and answers null when it is blank', function () {
    expect(UserResource::modelClass())->toBe(User::class);

    config()->set('wire-module-users.model', '');

    expect(UserResource::modelClass())->toBeNull();
});

it('answers no role model while roles are off', function () {
    config()->set('wire-module-users.roles', false);

    expect(RoleResource::modelClass())->toBeNull();
});

it('lets an application rename the menu group it ships', function () {
    config()->set('wire-module-users.navigation.label', 'Access control');

    expect((new UsersModule)->navigation()?->getLabel())->toBe('Access control');
});

it('reads a field name from configuration', function () {
    expect(UserResource::field('email'))->toBe('email');

    config()->set('wire-module-users.fields.email', 'email_address');

    expect(UserResource::field('email'))->toBe('email_address');
});

it('offers the roles and permissions that exist, and none when roles are off', function () {
    config()->set('wire-module-users.roles', false);

    expect(Roles::options())->toBe([])
        ->and(Roles::permissionOptions())->toBe([]);
});

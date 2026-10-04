<?php

declare(strict_types=1);

use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\AutoSwitch;
use NyonCode\WireModuleUsers\Support\Avatars;
use NyonCode\WireModuleUsers\Support\Passkeys;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\TwoFactor;

/*
 * The `true` / `false` / `'auto'` switches, read the way the environment writes
 * them. `env()` turns only the words `true` and `false` into booleans, so a
 * `1` used to arrive as the string "1", was not `'auto'`, and meant off.
 */

it('reads a switch as a boolean, auto, or nothing it understands', function (mixed $value, bool|string|null $read) {
    config()->set('wire-module-users.roles', $value);

    expect(AutoSwitch::read('wire-module-users.roles'))->toBe($read);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'env 1' => ['1', true],
    'env 0' => ['0', false],
    'env on' => ['on', true],
    'env off' => ['off', false],
    'int 1' => [1, true],
    'int 0' => [0, false],
    'auto' => ['auto', 'auto'],
    'AUTO, spaced' => [' AUTO ', 'auto'],
    'nonsense' => ['sometimes', null],
    'a list' => [['x'], null],
]);

it('defaults a switch that is not set at all to auto', function () {
    config()->set('wire-module-users', []);

    expect(AutoSwitch::read('wire-module-users.roles'))->toBe('auto');
});

it('asks detection only for auto, and turns anything unreadable off', function () {
    $asked = 0;
    $detect = function () use (&$asked): bool {
        $asked++;

        return true;
    };

    config()->set('wire-module-users.roles', 'auto');
    expect(AutoSwitch::on('wire-module-users.roles', $detect))->toBeTrue();

    config()->set('wire-module-users.roles', '1');
    expect(AutoSwitch::on('wire-module-users.roles', $detect))->toBeTrue();

    config()->set('wire-module-users.roles', 'sometimes');
    expect(AutoSwitch::on('wire-module-users.roles', $detect))->toBeFalse()
        ->and($asked)->toBe(1);
});

it('turns every surface on from a 1 in the environment', function () {
    foreach (['roles', 'avatar.enabled', 'two_factor', 'passkeys'] as $key) {
        config()->set("wire-module-users.{$key}", '1');
    }

    expect(Roles::enabled())->toBeTrue()
        ->and(Avatars::enabled())->toBeTrue()
        ->and(TwoFactor::enabled())->toBeTrue()
        ->and(Passkeys::enabled())->toBeTrue();
});

it('writes a blank field to its default column, as the screens read it', function () {
    config()->set('wire-module-users.fields', ['name' => '', 'email' => 'mail', 'password' => null]);

    expect(app(Accounts::class)->fields())->toBe(['name' => 'name', 'email' => 'mail', 'password' => 'password']);
});

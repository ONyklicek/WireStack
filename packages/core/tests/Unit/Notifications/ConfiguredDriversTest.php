<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Support\ConfiguredDrivers;

/*
 * `wire-core.notifications.default` is a list in a published config and a
 * comma-separated string from the environment — the shape `wire:install`
 * writes. Every reader must see the same names either way.
 */

it('reads a list as it is', function () {
    config()->set('wire-core.notifications.default', ['session', 'database']);

    expect(ConfiguredDrivers::names())->toBe(['session', 'database']);
});

it('splits a comma-separated string, as the environment carries it', function () {
    config()->set('wire-core.notifications.default', ' session, database ,broadcast,');

    expect(ConfiguredDrivers::names())->toBe(['session', 'database', 'broadcast'])
        ->and(ConfiguredDrivers::includes('database'))->toBeTrue()
        ->and(ConfiguredDrivers::includes('broadcast'))->toBeTrue()
        ->and(ConfiguredDrivers::includes('flasher'))->toBeFalse();
});

it('reads one name as one driver', function () {
    config()->set('wire-core.notifications.default', 'session');

    expect(ConfiguredDrivers::names())->toBe(['session']);
});

it('drops empty entries from a list', function () {
    config()->set('wire-core.notifications.default', ['session', '', ' database ']);

    expect(ConfiguredDrivers::names())->toBe(['session', 'database']);
});

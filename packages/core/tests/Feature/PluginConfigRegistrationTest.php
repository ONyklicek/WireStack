<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Exceptions\PluginRegistrationException;

/*
 * What `config('wire-core.plugins')` accepts, and what it now refuses.
 *
 * This is the path an application installs a domain module through, so a silent
 * skip here is a whole business area that does not exist: no menu entry, no
 * routes, no dashboards, and nothing anywhere saying why.
 */

final class PcrCountingPlugin implements Plugin
{
    public static int $registered = 0;

    public function getId(): string
    {
        return 'pcr-counting';
    }

    public function register(PluginManager $manager): void
    {
        self::$registered++;
    }

    public function boot(PluginManager $manager): void {}
}

/**
 * Re-resolve the manager so the provider's afterResolving callback reads config
 * again. Forgetting the instance is what makes the container build — and fire
 * the callback — a second time; config set inside a test does not survive an
 * application refresh, so this is the only way to exercise the real path.
 */
function pcrResolveWith(mixed $plugins): PluginManager
{
    config()->set('wire-core.plugins', $plugins);
    app()->forgetInstance(PluginManager::class);

    return app(PluginManager::class);
}

beforeEach(function () {
    PcrCountingPlugin::$registered = 0;
});

it('registers a plugin the application listed', function () {
    $manager = pcrResolveWith([PcrCountingPlugin::class]);

    expect($manager->has('pcr-counting'))->toBeTrue()
        ->and(PcrCountingPlugin::$registered)->toBe(1);
});

it('refuses a listed class that cannot be a plugin', function () {
    // Used to be skipped. A typo in a class name and a class that forgot the
    // contract look the same from here, and both left the application running
    // with one fewer module than its config declares.
    expect(fn () => pcrResolveWith([stdClass::class]))
        ->toThrow(PluginRegistrationException::class, 'does not implement');
});

it('says the class does not exist when that is the reason', function () {
    // The two mistakes need different sentences: one is a missing contract, the
    // other is a name nobody will find by re-reading the class.
    expect(fn () => pcrResolveWith(['App\\Modules\\NoSuchModule']))
        ->toThrow(PluginRegistrationException::class, 'the class does not exist');
});

it('skips a blank entry, which is a trailing comma rather than a declaration', function () {
    // The same tolerance ResourceRegistry::registerMany() extends to the same
    // mistake in a published config file.
    $manager = pcrResolveWith(['', PcrCountingPlugin::class]);

    expect($manager->has('pcr-counting'))->toBeTrue();
});

it('refuses a config value that is not a list at all', function () {
    // A published file edited down to a single class name, without the array.
    // The alternative is a PHP error raised inside a package provider for a
    // mistake that is one line of the application's config.
    expect(fn () => pcrResolveWith('not-an-array'))
        ->toThrow(PluginRegistrationException::class, 'must be an array');
});

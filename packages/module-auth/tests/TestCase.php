<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Tests;

use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireModuleAuth\WireModuleAuthServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Fortify is a real dependency here, not a detected one.
     *
     * The whole package is the answer to its seven view callbacks, so a suite
     * that stood it up without Fortify would be testing markup against nothing.
     * That is the opposite of `wire-module-users`, which detects Fortify and
     * works without it — there, the feature is optional; here, it is the engine.
     *
     * The shell is deliberately *not* registered by default: most of what these
     * screens do has to hold without it, and the cases that need a frame say so
     * for themselves.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireCoreServiceProvider::class,
            FortifyServiceProvider::class,
            WireModuleAuthServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // The monorepo runner sets the legacy CACHE_DRIVER, which Laravel 11+
        // ignores, so the default store falls back to `database` and there is no
        // cache table here. Set on the case rather than in phpunit.xml: these
        // suites are run both ways.
        $app['config']->set('cache.default', 'array');

        // Every feature on, so the screens that exist only behind one are
        // reachable. A test that needs a feature off turns it off itself, which
        // is also how an application meets this.
        $app['config']->set('fortify.features', [
            Features::registration(),
            Features::resetPasswords(),
            Features::emailVerification(),
            Features::updatePasswords(),
            Features::twoFactorAuthentication(),
        ]);
    }
}

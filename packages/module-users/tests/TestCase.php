<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests;

use Laravel\Fortify\FortifyServiceProvider;
use Livewire\LivewireServiceProvider;
use NyonCode\PermissionExtended\PermissionExtendedServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WireModuleUsers\WireModuleUsersServiceProvider;
use NyonCode\WirePanels\WirePanelsServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            // A dev dependency, and the two-factor card's whole point: it drives
            // Fortify's own actions, so a test that stubbed them would be
            // testing the stub.
            FortifyServiceProvider::class,
            PermissionServiceProvider::class,
            PermissionExtendedServiceProvider::class,
            WireCoreServiceProvider::class,
            WireFormsServiceProvider::class,
            WireTableServiceProvider::class,
            WirePanelsServiceProvider::class,
            WireModuleUsersServiceProvider::class,
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

        // The module runs over the application's user model; the tests bring one.
        $app['config']->set('wire-module-users.model', Fixtures\User::class);

        // And the auth provider has to point at the same model, or the permission
        // package cannot work out which guard a role belongs to — it derives that
        // from the providers map, and answers `GuardDoesNotMatch` otherwise.
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);

        // The permission package caches its lookup table, and Laravel's default
        // store is the database — which has no `cache` table here. Set on the
        // case rather than in this package's phpunit.xml, because the monorepo
        // runner uses its own: these tests passed alone and failed in the full
        // sweep, which is the worst way to find out.
        $app['config']->set('cache.default', 'array');

        // Fortify is installed here as a dev dependency, and its shipped config
        // turns two-factor on. That would make every other test in this package
        // run against an installation that happens to have it — so the feature
        // list starts empty and the tests that are about it switch it on.
        $app['config']->set('fortify.features', []);
    }
}

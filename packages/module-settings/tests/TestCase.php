<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Tests;

use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WireModuleSettings\WireModuleSettingsServiceProvider;
use NyonCode\WirePanels\WirePanelsServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireCoreServiceProvider::class,
            WireFormsServiceProvider::class,
            WireTableServiceProvider::class,
            WirePanelsServiceProvider::class,
            WireModuleSettingsServiceProvider::class,
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
    }
}

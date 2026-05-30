<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests;

use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
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
    }
}

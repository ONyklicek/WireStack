<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests;

use Laravel\Mcp\Server\McpServiceProvider;
use Livewire\LivewireServiceProvider;
use NyonCode\WireBoost\WireBoostServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\WireFormsServiceProvider;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            McpServiceProvider::class,
            WireCoreServiceProvider::class,
            WireFormsServiceProvider::class,
            WireTableServiceProvider::class,
            WireSortableServiceProvider::class,
            WireBoostServiceProvider::class,
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

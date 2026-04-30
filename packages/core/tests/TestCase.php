<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tests;

use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireCoreServiceProvider::class,
        ];
    }
}

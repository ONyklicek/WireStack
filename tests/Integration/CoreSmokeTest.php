<?php

declare(strict_types=1);

use NyonCode\WireCore\WireCoreServiceProvider;

test('wire-core service provider boots without errors', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(WireCoreServiceProvider::class);
});

test('wire-core config is published', function () {
    $config = config('wire-core');

    expect($config)->toBeArray();
});

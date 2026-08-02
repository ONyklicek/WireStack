<?php

declare(strict_types=1);

use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Support\PollingConfig;

/**
 * A table's polling settings on their own, without a table around them — the
 * eight properties that used to sit among the other seventy on `Table`.
 */
it('is off until asked', function () {
    $config = new PollingConfig;

    expect($config->isEnabled())->toBeFalse()
        ->and($config->interval())->toBeNull()
        ->and($config->directive())->toBeNull()
        ->and($config->getChangeDetection())->toBeFalse()
        ->and($config->isBroadcasting())->toBeFalse();
});

it('defaults to a soft refresh that stops with the tab', function () {
    $config = new PollingConfig;

    expect($config->getMethod())->toBe('refresh')
        ->and($config->isOnlyVisible())->toBeTrue()
        ->and($config->isKeepAlive())->toBeFalse()
        ->and($config->getCondition())->toBeNull();
});

it('turns polling on with the interval', function () {
    $config = (new PollingConfig)->every('15s');

    expect($config->isEnabled())->toBeTrue()
        ->and($config->interval())->toBe('15s')
        ->and($config->directive())->toBe('wire:poll.15s.visible');
});

it('accepts every Livewire interval unit', function (string $interval) {
    expect((new PollingConfig)->every($interval)->interval())->toBe($interval);
})->with(['750ms', '5s', '2m', '1h']);

it('refuses an interval Livewire would not understand', function () {
    (new PollingConfig)->every('5 seconds');
})->throws(TableConfigurationException::class);

it('carries every modifier into the attribute', function () {
    $config = (new PollingConfig)->every('10s')->keepAlive()->onlyVisible(false);

    expect($config->directive())->toBe('wire:poll.10s.keep-alive');
});

it('stays silent while polling is off, however it is configured', function () {
    // keepAlive() and friends do not imply polling — only every() does.
    $config = (new PollingConfig)->keepAlive()->method('reload');

    expect($config->directive())->toBeNull();
});

it('remembers the rest of the policy', function () {
    $condition = fn () => true;
    $detector = fn () => 'checksum';

    $config = (new PollingConfig)
        ->method('reload')
        ->when($condition)
        ->detectChanges($detector)
        ->broadcast();

    expect($config->getMethod())->toBe('reload')
        ->and($config->getCondition())->toBe($condition)
        ->and($config->getChangeDetection())->toBe($detector)
        ->and($config->isBroadcasting())->toBeTrue();
});

it('hands the view a shape it can read straight through', function () {
    $config = (new PollingConfig)->every('5s')->keepAlive()->method('reload');

    expect($config->toArray())->toBe([
        'enabled' => true,
        'interval' => '5s',
        'keepAlive' => true,
        'method' => 'reload',
        'onlyVisible' => true,
        'directive' => 'wire:poll.5s.keep-alive.visible',
    ]);
});

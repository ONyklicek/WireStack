<?php

declare(strict_types=1);

use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/*
 * The table's polling surface. The settings themselves are PollingConfig's
 * (tested next door); what is asserted here is that every one of the eighteen
 * public methods still reaches them, since the whole point of the extraction
 * was that the signatures do not move.
 */

it('is off on a table that never mentioned polling', function () {
    $table = Table::make();

    expect($table->isPolling())->toBeFalse()
        ->and($table->getPollingInterval())->toBeNull()
        ->and($table->getPollingDirective())->toBeNull()
        ->and($table->getPollingCondition())->toBeNull()
        ->and($table->getPollChangeDetection())->toBeFalse()
        ->and($table->shouldBroadcastChanges())->toBeFalse()
        ->and($table->getPollingMethod())->toBe('refresh')
        ->and($table->isPollingOnlyVisible())->toBeTrue()
        ->and($table->isPollingKeepAlive())->toBeFalse();
});

it('carries the whole fluent chain through to the directive', function () {
    $table = Table::make()->poll('10s')->pollKeepAlive()->pollMethod('reload');

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('10s')
        ->and($table->isPollingKeepAlive())->toBeTrue()
        ->and($table->getPollingMethod())->toBe('reload')
        ->and($table->getPollingDirective())->toBe('wire:poll.10s.keep-alive.visible');
});

it('drops the visible modifier when told to keep ticking in the background', function () {
    expect(Table::make()->poll('5s')->pollOnlyVisible(false)->getPollingDirective())
        ->toBe('wire:poll.5s');
});

it('remembers the condition a poll tick is gated on', function () {
    $condition = fn ($component) => $component->shouldRefresh;

    $table = Table::make()->poll()->pollWhen($condition);

    expect($table->getPollingCondition())->toBe($condition);
});

it('takes a custom change detector as well as the built-in one', function () {
    $detector = fn ($query) => (string) $query->max('synced_at');

    expect(Table::make()->pollChangeDetection()->getPollChangeDetection())->toBeTrue()
        ->and(Table::make()->pollChangeDetection($detector)->getPollChangeDetection())->toBe($detector)
        ->and(Table::make()->pollChangeDetection(false)->getPollChangeDetection())->toBeFalse();
});

it('makes live() one call for interval plus change detection', function () {
    $table = Table::make()->live('2s');

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('2s')
        ->and($table->getPollChangeDetection())->toBeTrue()
        ->and($table->shouldBroadcastChanges())->toBeFalse();
});

it('adds the broadcast push on top of the interval, never instead of it', function () {
    $table = Table::make()->live(broadcast: true);

    expect($table->shouldBroadcastChanges())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('5s')
        ->and($table->getPollingDirective())->toBe('wire:poll.5s.visible');
});

it('hands the view the shape it has always had', function () {
    expect(Table::make()->poll('30s')->getPollingConfig())->toBe([
        'enabled' => true,
        'interval' => '30s',
        'keepAlive' => false,
        'method' => 'refresh',
        'onlyVisible' => true,
        'directive' => 'wire:poll.30s.visible',
    ]);
});

it('rejects an interval Livewire could not parse', function () {
    Table::make()->poll('soon');
})->throws(TableConfigurationException::class);

it('still answers to the deprecated polling() name', function () {
    $table = @Table::make()->polling('15s');

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('15s');
});

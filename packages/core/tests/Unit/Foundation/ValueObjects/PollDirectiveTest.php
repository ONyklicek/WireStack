<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\ValueObjects\PollDirective;

/**
 * The `wire:poll` vocabulary, now owned once instead of written out in the
 * widget concern and again in the table. Only the attribute lives here — the
 * question of whether polling is on at all belongs to each surface.
 */
it('builds the attribute in the order Livewire reads it', function () {
    $directive = new PollDirective('10s', keepAlive: true, onlyVisible: true);

    expect((string) $directive)->toBe('wire:poll.10s.keep-alive.visible');
});

it('omits a modifier that is off', function () {
    expect((string) new PollDirective('5s', keepAlive: false, onlyVisible: false))
        ->toBe('wire:poll.5s');
});

it('polls on Livewire\'s own cadence when no interval was given', function () {
    // The table can be polling with no interval set; a bare `wire:poll` is a
    // valid attribute and means Livewire's default. The widget never reaches
    // this because it treats a missing interval as "not polling" instead.
    expect((string) new PollDirective(null, onlyVisible: false))->toBe('wire:poll');
    expect((string) new PollDirective('', onlyVisible: false))->toBe('wire:poll');
});

it('defaults to visible-only, the cheaper of the two', function () {
    expect((string) new PollDirective('30s'))->toBe('wire:poll.30s.visible');
});

it('keeps its parts readable', function () {
    $directive = new PollDirective('1m', keepAlive: true, onlyVisible: false);

    expect($directive->interval)->toBe('1m')
        ->and($directive->keepAlive)->toBeTrue()
        ->and($directive->onlyVisible)->toBeFalse();
});

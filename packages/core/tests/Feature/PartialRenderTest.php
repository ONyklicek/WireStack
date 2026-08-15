<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;

/**
 * Rendering a named region instead of the view.
 *
 * The mechanism islands cannot provide: an island's name is re-evaluated inside
 * its own compiled view file, which never sees a loop variable, so there is no
 * island per record. A partial is chosen by the server at write time and named
 * by an ordinary HTML attribute, so it costs nothing per row.
 *
 * Everything below is about the **coverage rule**, because that is the part that
 * decides whether a partial response is correct rather than merely small: the
 * view is skipped only when every call queued a region and no property update
 * happened. A response that covered half of what changed is exactly the stale
 * number this work exists to avoid.
 */
class PrtHost extends Component
{
    use InteractsWithPartials;

    public int $renders = 0;

    public string $name = '';

    /** Writes one record and re-renders only that record's region. */
    public function writeOne(string $value): void
    {
        $this->renderPartial('row-1', fn (): string => '<tr wire:partial="row-1"><td>'.$value.'</td></tr>');
    }

    /** Writes one record and moves something outside it — two regions, one call. */
    public function writeAndTotal(string $value): void
    {
        $this->renderPartial('row-1', fn (): string => '<tr wire:partial="row-1"><td>'.$value.'</td></tr>');
        $this->renderPartial('total', fn (): string => '<span wire:partial="total">'.$value.'</span>');
    }

    /** A write that says nothing about what it changed. */
    public function writeBlind(): void {}

    public function render(): string
    {
        $this->renders++;

        return '<div>{{ $this->renders }}</div>';
    }
}

it('renders the region a write asked for, and not the view', function () {
    $test = Livewire::test(PrtHost::class);

    $test->call('writeOne', 'edited');

    expect($test->instance()->renders)->toBe(1)                   // mounted only
        ->and($test->effects['wirePartials'] ?? null)->toBeArray()
        ->and($test->effects['wirePartials'])->toHaveKey('row-1')
        ->and($test->effects['wirePartials']['row-1'])->toContain('edited')
        // The view did not run, so there is no html to morph over the top of it.
        ->and($test->effects['html'] ?? null)->toBeNull();
});

it('carries every region one call queued', function () {
    // A write whose effect reaches past the row it wrote — a total, a subtotal —
    // says so by queueing that region too. This is the whole discipline: what is
    // not named is not sent, and what is not sent is stale.
    $test = Livewire::test(PrtHost::class)->call('writeAndTotal', 'x');

    expect(array_keys($test->effects['wirePartials']))->toBe(['row-1', 'total'])
        ->and($test->instance()->renders)->toBe(1);
});

it('renders the view for a call that named no region', function () {
    // The default, and it has to be the default: a call that says nothing about
    // what it touched gets the full render.
    $test = Livewire::test(PrtHost::class)->call('writeBlind');

    expect($test->instance()->renders)->toBe(2)
        ->and($test->effects['wirePartials'] ?? null)->toBeNull();
});

it('renders the view when one call in the request named nothing', function () {
    // Two calls in one request is ordinary — a cell edit on the way to a
    // pagination click. One of them uncovered makes the whole response
    // uncoverable, and partials are dropped rather than sent alongside a partial
    // truth.
    $test = Livewire::test(PrtHost::class);

    $test->call('writeOne', 'edited');
    expect($test->instance()->renders)->toBe(1);

    // Fresh request, both calls: covered + uncovered.
    $second = Livewire::test(PrtHost::class);
    $second->call('writeOne', 'edited');
    $second->call('writeBlind');

    expect($second->instance()->renders)->toBe(2)
        ->and($second->effects['wirePartials'] ?? null)->toBeNull();
});

it('renders the view when a property was updated', function () {
    // A property update can change anything the view shows — a filter, a page, a
    // sort — so no set of regions can be said to cover it.
    $test = Livewire::test(PrtHost::class)->set('name', 'changed');

    expect($test->instance()->renders)->toBe(2)
        ->and($test->effects['wirePartials'] ?? null)->toBeNull();
});

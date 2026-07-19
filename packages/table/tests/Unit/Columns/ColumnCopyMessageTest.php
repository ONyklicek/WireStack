<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\TextColumn;

/**
 * copyMessage resolution fuse (render-engine-htmlable-first.md §5).
 *
 * The copyable message default was resolved through the translator on every cell —
 * even for non-copyable columns, which never use it — so a plain N-row table paid N
 * wasted `Trans::get('…copied')` calls. renderCell now resolves it only when the
 * column is copyable. This counts the resolutions via a pass-through translator
 * decorator and asserts a non-copyable column makes zero.
 */

/**
 * Swap the translator for a decorator that counts how many times the copyable
 * default key is resolved, delegating everything else to the real translator so
 * output is unchanged. Returns a closure yielding the current count.
 */
function copiedKeyCounter(): Closure
{
    $real = app('translator');

    $counter = new class($real)
    {
        public int $count = 0;

        public function __construct(public mixed $inner) {}

        public function __call(string $method, array $args): mixed
        {
            if ($method === 'get' && ($args[0] ?? null) === 'wire-table::messages.copied') {
                $this->count++;
            }

            return $this->inner->{$method}(...$args);
        }
    };

    app()->instance('translator', $counter);

    return fn (): int => $counter->count;
}

function copyRecord(array $attributes): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes);

    return $record;
}

it('never resolves the copyable default for a non-copyable column', function () {
    $count = copiedKeyCounter();

    $column = TextColumn::make('name');
    for ($i = 1; $i <= 5; $i++) {
        $column->renderCell(copyRecord(['name' => 'User '.$i]));
    }

    expect($count())->toBe(0);
});

it('renders the copy affordance for a copyable column', function () {
    $html = TextColumn::make('name')->copyable()->renderCell(copyRecord(['name' => 'Ada']));

    // The copy button still reaches the cell — fewer lookups, same output.
    expect($html)->toContain('data-testid="cell-copy"');
});

it('keeps a custom copy message on a copyable column', function () {
    $html = TextColumn::make('name')->copyable(copyMessage: 'Grabbed!')
        ->renderCell(copyRecord(['name' => 'Ada']));

    expect($html)->toContain('Grabbed!');
});

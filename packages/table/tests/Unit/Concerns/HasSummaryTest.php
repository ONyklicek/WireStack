<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\Column;

/**
 * Helper: build a page-scope summary of $type on column $name over $rows,
 * returning the single computed value.
 */
function summaryValue(string $name, string $type, array $rows, ?Closure $configure = null): mixed
{
    $column = Column::make($name)->summarize($type, scope: 'page');

    if ($configure) {
        $configure($column);
    }

    $result = $column->computeSummaries(collect($rows));

    return $result[0]['value'];
}

// ─── New aggregate types (in-memory) ─────────────────────────────────────────

it('computes distinctCount', function () {
    $rows = [['v' => 1], ['v' => 1], ['v' => 2], ['v' => 3], ['v' => 3]];

    expect(summaryValue('v', 'distinctCount', $rows))->toBe(3);
});

it('computes median for odd count', function () {
    $rows = [['v' => 5], ['v' => 1], ['v' => 3]];

    expect(summaryValue('v', 'median', $rows))->toBe(3.0);
});

it('computes median for even count as average of middle two', function () {
    $rows = [['v' => 1], ['v' => 2], ['v' => 3], ['v' => 4]];

    expect(summaryValue('v', 'median', $rows))->toBe(2.5);
});

it('computes sample variance', function () {
    // values 2,4,4,4,5,5,7,9 → sample variance = 32/7 ≈ 4.571 (n-1)
    $rows = collect([2, 4, 4, 4, 5, 5, 7, 9])->map(fn ($v) => ['v' => $v])->all();

    expect(summaryValue('v', 'variance', $rows))->toBe(4.57);
});

it('computes sample standard deviation', function () {
    $rows = collect([2, 4, 4, 4, 5, 5, 7, 9])->map(fn ($v) => ['v' => $v])->all();

    // sqrt(4.571...) ≈ 2.138 → rounded 2.14
    expect(summaryValue('v', 'stddev', $rows))->toBe(2.14);
});

it('returns zero variance for a single value', function () {
    expect(summaryValue('v', 'variance', [['v' => 42]]))->toBe(0.0);
});

it('computes first and last', function () {
    $rows = [['v' => 'a'], ['v' => 'b'], ['v' => 'c']];

    expect(summaryValue('v', 'first', $rows))->toBe('a')
        ->and(summaryValue('v', 'last', $rows))->toBe('c');
});

// ─── Empty / null handling ───────────────────────────────────────────────────

it('returns 0 for sum over empty set', function () {
    expect(summaryValue('v', 'sum', []))->toBe(0);
});

it('returns 0 for count over empty set', function () {
    expect(summaryValue('v', 'count', []))->toBe(0);
});

it('ignores null values when aggregating', function () {
    $rows = [['v' => 10], ['v' => null], ['v' => 20]];

    expect(summaryValue('v', 'sum', $rows))->toBe(30)
        ->and(summaryValue('v', 'count', $rows))->toBe(2);
});

// ─── Numeric formatting (A1) ─────────────────────────────────────────────────

it('formats numeric summaries with decimals and separators', function () {
    $value = summaryValue('price', 'sum', [['price' => 1000.5], ['price' => 234]], function (Column $c) {
        $c->summaryDecimals(2);
    });

    expect($value)->toBe('1 234,50');
});

it('applies prefix and suffix to numeric summaries', function () {
    $column = Column::make('price')
        ->prefix('$')
        ->summaryDecimals(2, '.', ',')
        ->summarize('sum', scope: 'page');

    $value = $column->computeSummaries(collect([['price' => 1000], ['price' => 500]]))[0]['value'];

    expect($value)->toBe('$1,500.00');
});

it('does not reformat counts as decimals', function () {
    $value = summaryValue('v', 'count', [['v' => 1], ['v' => 2]], function (Column $c) {
        $c->summaryDecimals(2);
    });

    expect($value)->toBe(2);
});

it('leaves numbers untouched when no decimals configured', function () {
    expect(summaryValue('v', 'sum', [['v' => 10], ['v' => 5]]))->toBe(15);
});

// ─── Conditional aggregation via when() (A3) ─────────────────────────────────

it('restricts in-memory aggregation with a when() predicate', function () {
    $rows = [
        ['amount' => 100, 'paid' => true],
        ['amount' => 50, 'paid' => false],
        ['amount' => 200, 'paid' => true],
    ];

    $column = Column::make('amount')->summarize(
        'sum',
        scope: 'page',
        when: fn ($value, $record) => (bool) data_get($record, 'paid'),
    );

    expect($column->computeSummaries(collect($rows))[0]['value'])->toBe(300);
});

// ─── Explicit format closure still wins ──────────────────────────────────────

it('lets an explicit format closure override default formatting', function () {
    $column = Column::make('v')
        ->summaryDecimals(2)
        ->summarize('sum', scope: 'page', format: fn ($v) => "total={$v}");

    expect($column->computeSummaries(collect([['v' => 3], ['v' => 4]]))[0]['value'])->toBe('total=7');
});

// ─── Custom closure type ─────────────────────────────────────────────────────

it('supports a closure summary type over the value collection', function () {
    $column = Column::make('v')->summarize(
        fn (Collection $values) => $values->max() - $values->min(),
        scope: 'page',
    );

    expect($column->computeSummaries(collect([['v' => 3], ['v' => 10], ['v' => 7]]))[0]['value'])->toBe(7);
});

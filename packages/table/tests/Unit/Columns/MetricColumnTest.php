<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireTable\Columns\MetricColumn;
use NyonCode\WireTable\Columns\MoneyColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Support\MobileCard;

/*
 * A metric cell: the figure, and optionally the trend behind it.
 *
 * The aggregation is not this column's — the relation AST already turns
 * `orders.count` into a withCount subquery — and neither is the geometry, which
 * belongs to Foundation\View\Sparkline and is shared with the stats widget. What
 * is tested here is what the column decides: when a trend is drawn at all, what
 * a series has to look like to be drawn, and the figure defaults it shares with
 * MoneyColumn through RendersAsFigure.
 */

class MetricRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function metricRecord(array $attributes = []): MetricRow
{
    return new MetricRow($attributes + ['orders_count' => 12]);
}

// ─── The figure defaults, shared with MoneyColumn ────────────────────────────

it('reads as a figure, exactly as a money column does', function () {
    $metric = MetricColumn::make('orders_count');

    expect($metric->getAlignment())->toBe('right')
        ->and($metric->getTextClasses())->toContain('tabular-nums')
        ->toContain('whitespace-nowrap')
        // One concern owns those defaults, so the two types cannot drift apart.
        ->and($metric->getTextClasses())->toBe(MoneyColumn::make('total')->getTextClasses());
});

it('becomes the metric a stacked card is read for', function () {
    // The same consequence the alignment carries for money: MobileCard picks the
    // last right-aligned column as the card's headline figure.
    $card = MobileCard::resolve([
        TextColumn::make('name'),
        TextColumn::make('email'),
        MetricColumn::make('orders_count'),
    ]);

    expect($card->metric()?->getName())->toBe('orders_count');
});

// ─── When a trend is drawn ───────────────────────────────────────────────────

it('is a plain figure cell until a trend is asked for', function () {
    $plain = MetricColumn::make('orders_count');

    expect($plain->hasTrend())->toBeFalse()
        // Asked for the series anyway — by an export, a test, a custom view — it
        // answers "none" rather than calling a callback that is not there.
        ->and($plain->getTrend(metricRecord()))->toBe([])
        // No wrapper, no svg — a metric without a trend costs what a text cell costs.
        ->and($plain->renderCell(metricRecord()))->not->toContain('<svg')
        ->and($plain->renderCell(metricRecord()))->toContain('12');
});

it('draws the series beside the figure', function () {
    $column = MetricColumn::make('orders_count')
        ->trend(fn (MetricRow $record): array => [1, 5, 3]);

    $html = $column->renderCell(metricRecord());

    expect($html)->toContain('<svg')
        ->toContain('viewBox="0 0 20 30"')
        ->toContain('points="0,30 10,2 20,16"')
        // The number is still the thing being read.
        ->toContain('12')
        // The curve says nothing the figure does not already say.
        ->toContain('aria-hidden="true"');
});

it('draws nothing for a record with no history', function () {
    $column = MetricColumn::make('orders_count')
        ->trend(fn (MetricRow $record): array => []);

    // An empty series is not an empty box: the cell falls back to the figure.
    expect($column->renderCell(metricRecord()))->not->toContain('<svg');
});

it('drops readings that are not numbers rather than plotting them as zero', function () {
    $column = MetricColumn::make('orders_count')
        ->trend(fn (MetricRow $record): array => [4, null, 4, 'n/a']);

    // A null month means "no reading". Coerced to 0 it would invent a crash to
    // the floor and back; dropped, the series is the two readings there were.
    expect($column->getTrend(metricRecord()))->toBe([4, 4])
        ->and($column->renderCell(metricRecord()))->toContain('points="0,16 10,16"');
});

it('survives a trend callback that returns something else entirely', function () {
    $column = MetricColumn::make('orders_count')
        ->trend(fn (MetricRow $record): mixed => null);

    expect($column->getTrend(metricRecord()))->toBe([])
        ->and($column->renderCell(metricRecord()))->not->toContain('<svg');
});

it('reads the series from the record it is given', function () {
    $column = MetricColumn::make('orders_count')
        ->trend(fn (MetricRow $record): array => $record->history ?? []);

    expect($column->getTrend(metricRecord(['history' => [2, 8]])))->toBe([2, 8])
        ->and($column->getTrend(metricRecord()))->toBe([]);
});

// ─── The stroke ──────────────────────────────────────────────────────────────

it('draws the trend in the palette, defaulting to the column colour', function () {
    $default = MetricColumn::make('orders_count')->trend(fn (): array => [1, 2]);
    $stated = MetricColumn::make('orders_count')->trend(fn (): array => [1, 2], 'danger');

    expect($default->getTrendColorClass())->toBe(
        HasColor::getFillTextClasses('primary'),
    )->and($stated->getTrendColorClass())->toBe(
        HasColor::getFillTextClasses('danger'),
    );

    // The column's own colour stands in when the trend does not state one.
    expect(MetricColumn::make('orders_count')->color('success')->trend(fn (): array => [1, 2])->getTrendColorClass())
        ->toBe(HasColor::getFillTextClasses('success'));
});

it('respects the per-record visibility gate like any other cell', function () {
    // The same guard BadgeColumn's cell consults: visibleForRecord() is the
    // per-row one, while visible() is structural and decided by the table.
    $hidden = MetricColumn::make('orders_count')
        ->trend(fn (): array => [1, 2])
        ->visibleForRecord(fn (MetricRow $record): bool => $record->orders_count > 100);

    expect($hidden->renderCell(metricRecord()))->toBe('')
        ->and($hidden->renderCell(metricRecord(['orders_count' => 500])))->toContain('<svg');
});

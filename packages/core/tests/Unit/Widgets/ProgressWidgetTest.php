<?php

declare(strict_types=1);

use NyonCode\WireCore\Exceptions\InvalidWidgetDataException;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;

// ─── The series ──────────────────────────────────────────────────────────────

it('takes a series of progress items', function () {
    $widget = ProgressWidget::make()->items([
        ProgressItem::make('New MRR')->value(84_000)->target(120_000),
        ProgressItem::make('Churn budget')->value(31)->target(40),
    ]);

    expect($widget->getItems())->toHaveCount(2)
        ->and($widget->getItems()[0]->getLabel())->toBe('New MRR');
});

it('refuses an entry of the wrong class at the line that wrote it', function () {
    ProgressWidget::make()->items([ChartItem::make('Nope')]);
})->throws(InvalidWidgetDataException::class, 'ProgressWidget::items() expects an array of');

// ─── Rendering ───────────────────────────────────────────────────────────────

it('draws a fill whose width is the item percentage', function () {
    $html = ProgressWidget::make()
        ->items([ProgressItem::make('New MRR')->value(30)->target(120)])
        ->toHtml();

    expect($html)->toContain('style="width: 25%"')
        // The reading is on the track for anything that cannot see it.
        ->and($html)->toContain('aria-valuenow="25"')
        ->and($html)->toContain('role="progressbar"')
        ->and($html)->toContain('New MRR');
});

it('prints each value unless asked not to', function () {
    $items = [ProgressItem::make('New MRR')->value(30)->target(120)];

    expect(ProgressWidget::make()->items($items)->toHtml())->toContain('25%')
        ->and(ProgressWidget::make()->items($items)->showValues(false)->toHtml())->not->toContain('>25%')
        ->and(ProgressWidget::make()->showsValues())->toBeTrue();
});

it('shows an empty state rather than an empty card', function () {
    expect(ProgressWidget::make()->items([])->toHtml())->toContain('Nothing to show.')
        ->and(ProgressWidget::make()->emptyState('No targets set.')->toHtml())
        ->toContain('No targets set.');
});

it('draws its heading, description and item description', function () {
    $html = ProgressWidget::make()
        ->heading('Quarterly targets')
        ->description('Against plan')
        ->items([ProgressItem::make('New MRR')->description('vs. last quarter')])
        ->toHtml();

    expect($html)->toContain('Quarterly targets')
        ->and($html)->toContain('Against plan')
        ->and($html)->toContain('vs. last quarter');
});

it('puts an item extra attribute on its row', function () {
    $html = ProgressWidget::make()
        ->items([ProgressItem::make('New MRR')->extraAttributes(['data-testid' => 'mrr'])])
        ->toHtml();

    expect($html)->toContain('data-testid="mrr"');
});

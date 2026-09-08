<?php

declare(strict_types=1);

use NyonCode\WireCore\Exceptions\InvalidWidgetDataException;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;

// ─── The series ──────────────────────────────────────────────────────────────

it('takes a series of list items', function () {
    $widget = ListWidget::make()->items([
        ListItem::make('Order #1042'),
        ListItem::make('Order #1041'),
    ]);

    expect($widget->getItems())->toHaveCount(2)
        ->and($widget->hasItems())->toBeTrue();
});

it('refuses an entry of the wrong class', function () {
    ListWidget::make()->items([ChartItem::make('Nope')]);
})->throws(InvalidWidgetDataException::class, 'ListWidget::items() expects an array of');

// ─── Rendering ───────────────────────────────────────────────────────────────

it('draws a title, a description and an aside', function () {
    $html = ListWidget::make()
        ->items([
            ListItem::make('Order #1042')->description('Acme s.r.o.')->meta('2 minutes ago'),
        ])
        ->toHtml();

    expect($html)->toContain('Order #1042')
        ->and($html)->toContain('Acme s.r.o.')
        ->and($html)->toContain('2 minutes ago');
});

it('makes the whole entry the link, not just its title', function () {
    // A link covering only the title gives a pointing device a target three
    // pixels tall.
    $html = ListWidget::make()
        ->items([ListItem::make('Order #1042')->description('Acme s.r.o.')->url('/orders/1042')])
        ->toHtml();

    expect($html)->toContain('href="/orders/1042"')
        ->and($html)->toContain('Acme s.r.o.</span>');
});

it('carries noopener with every new tab', function () {
    // Without it the opened page can reach back through window.opener.
    $html = ListWidget::make()
        ->items([ListItem::make('a')->url('https://example.test')->newTab()])
        ->toHtml();

    expect($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="noopener noreferrer"');
});

it('renders an unlinked entry as a plain row', function () {
    $html = ListWidget::make()->items([ListItem::make('Order #1042')])->toHtml();

    expect($html)->not->toContain('<a href');
});

it('divides entries unless told not to', function () {
    $items = [ListItem::make('a'), ListItem::make('b')];

    expect(ListWidget::make()->items($items)->toHtml())->toContain('divide-y')
        ->and(ListWidget::make()->items($items)->dividers(false)->toHtml())->not->toContain('divide-y')
        ->and(ListWidget::make()->hasDividers())->toBeTrue();
});

it('shows an empty state rather than an empty card', function () {
    expect(ListWidget::make()->items([])->toHtml())->toContain('Nothing to show.')
        ->and(ListWidget::make()->emptyState('No orders yet.')->toHtml())->toContain('No orders yet.')
        ->and(ListWidget::make()->emptyState('x')->emptyState(null)->getEmptyState())
        ->toBe('Nothing to show.');
});

it('puts an item extra attribute on its row', function () {
    $html = ListWidget::make()
        ->items([ListItem::make('a')->extraAttributes(['data-testid' => 'first'])])
        ->toHtml();

    expect($html)->toContain('data-testid="first"');
});

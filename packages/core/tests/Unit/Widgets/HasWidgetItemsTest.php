<?php

declare(strict_types=1);

use NyonCode\WireCore\Exceptions\InvalidWidgetDataException;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;
use NyonCode\WireCore\Widgets\ProgressItem;

/**
 * The one owner of "a series, or a closure resolving one".
 *
 * Three widget families draw a list of value objects, and before this trait each
 * of them would have retyped taking it, validating it and saying what an empty
 * one looks like. What is exercised here is the shared behaviour; the widgets'
 * own tests exercise what they do with the result.
 */

// ─── Validation happens at both moments it can ───────────────────────────────

it('validates an array on the line that declared it', function () {
    ListWidget::make()->items([ListItem::make('ok'), ProgressItem::make('wrong')]);
})->throws(InvalidWidgetDataException::class);

it('validates a closure result on render, which is the only moment it exists', function () {
    // Declaring it cannot throw — there is nothing to look at yet.
    $widget = ListWidget::make()->items(fn () => [ChartItem::make('wrong')]);

    expect(fn () => $widget->getItems())->toThrow(InvalidWidgetDataException::class);
});

it('names the widget and the class it wanted', function () {
    expect(fn () => ListWidget::make()->items([ProgressItem::make('wrong')]))
        ->toThrow(
            InvalidWidgetDataException::class,
            'ListWidget::items() expects an array of NyonCode\WireCore\Widgets\ListItem instances.',
        );
});

it('reindexes a sparse array so the view can loop it', function () {
    $widget = ListWidget::make()->items([3 => ListItem::make('a'), 7 => ListItem::make('b')]);

    expect(array_keys($widget->getItems()))->toBe([0, 1]);
});

// ─── The closure sees the filter ─────────────────────────────────────────────

it('hands the active filter key to the closure', function () {
    $widget = ListWidget::make()
        ->filter(['week' => 'This week', 'month' => 'This month'])
        ->items(fn (?string $filter) => [ListItem::make('range: '.$filter)]);

    expect($widget->getItems()[0]->getTitle())->toBe('range: week');

    $widget->activeFilter('month');

    expect($widget->getItems()[0]->getTitle())->toBe('range: month');
});

it('runs the closure on every call rather than memoizing it', function () {
    // Memoizing would save one call and hide a closure that is not idempotent.
    $calls = 0;
    $widget = ListWidget::make()->items(function () use (&$calls) {
        $calls++;

        return [];
    });

    $widget->getItems();
    $widget->getItems();

    expect($calls)->toBe(2);
});

// ─── Empty state ─────────────────────────────────────────────────────────────

it('has a translated default empty state and takes an override', function () {
    expect(ListWidget::make()->getEmptyState())->toBe('Nothing to show.')
        ->and(ListWidget::make()->emptyState('No orders yet.')->getEmptyState())->toBe('No orders yet.')
        ->and(ListWidget::make()->hasItems())->toBeFalse();
});

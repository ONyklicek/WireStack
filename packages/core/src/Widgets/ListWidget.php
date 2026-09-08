<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use NyonCode\WireCore\Widgets\Concerns\HasWidgetItems;

/**
 * A short feed: the last few records, the last few things that happened.
 *
 * The surface a dashboard reaches for after its figures — "37 new orders" is the
 * stat, and *which* orders is this. {@see TableWidget} answers a bigger version
 * of the same question and brings a table's whole apparatus with it: columns,
 * sorting, pagination, a toolbar. Ten rows of title-and-timestamp need none of
 * that, and a table shrunk until it fits a dashboard card is still a table.
 *
 * Pure CSS and pure markup — no JavaScript, no round trip to draw. A row with a
 * url is an anchor, so the feed works with JavaScript off.
 *
 * ```php
 * ListWidget::make()
 *     ->heading('Recent orders')
 *     ->items(fn () => Order::latest()->limit(5)->get()
 *         ->map(fn (Order $order) => ListItem::make($order->reference)
 *             ->description($order->customer->name)
 *             ->meta($order->created_at->diffForHumans())
 *             ->color($order->status->color())
 *             ->url(route('orders.show', $order)))
 *         ->all());
 * ```
 *
 * `->limit(5)` in the query rather than a `->maxItems(5)` on the widget,
 * deliberately: a widget that trimmed the series would let a caller load a
 * thousand rows to draw five, and the place that knows how many are wanted is
 * the query.
 */
class ListWidget extends Widget
{
    use HasWidgetItems;

    protected bool $dividers = true;

    /** Draw a hairline between entries — on by default. */
    public function dividers(bool $condition = true): static
    {
        $this->dividers = $condition;

        return $this;
    }

    public function hasDividers(): bool
    {
        return $this->dividers;
    }

    /**
     * @return class-string<ListItem>
     */
    protected function itemClass(): string
    {
        return ListItem::class;
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.list';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'items' => $this->getItems(),
            'dividers' => $this->dividers,
            'filterOptions' => $this->getFilterOptions(),
            'activeFilter' => $this->getActiveFilter(),
            'filterExpression' => $this->getFilterExpression(),
        ];
    }
}

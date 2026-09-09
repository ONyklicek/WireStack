<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use Closure;
use NyonCode\WireCore\Exceptions\InvalidWidgetDataException;
use NyonCode\WireCore\Widgets\BarChartWidget;

/**
 * A widget draws a series of things, given as an array or resolved on render.
 *
 * Three widget families draw a list of value objects — bars, progress rows,
 * feed entries — and each of them needs the same three behaviours: take the
 * series, refuse entries of the wrong class, and resolve a closure against the
 * active filter. Written once here rather than three times, which is the rule
 * `CLAUDE.md` states for any capability shared across component types.
 *
 * What an empty series *says* is not here: {@see HasEmptyState} owns that, on
 * the widget base, because a widget with no series at all can still come back
 * with nothing to draw.
 *
 * ## Where the validation happens, and why twice
 *
 * An array is validated in {@see items()}, at the moment of declaration, so a
 * mistyped entry fails on the line that wrote it. A closure cannot be checked
 * then — there is nothing to check yet — so its result is validated in
 * {@see getItems()}, on every render. Neither check is redundant: they catch
 * the same mistake at the only two moments where each *can* be caught.
 *
 * ## The closure sees the filter
 *
 * `fn (?string $filter) => …` receives the widget's active filter key, which is
 * what makes {@see HasWidgetFilter} worth anything on a list: the host records
 * the user's choice, pushes it back into the widget, and this closure runs
 * again with it. Ignore the argument for a series that does not vary.
 *
 * ## Not a cache
 *
 * The closure runs on every call, not once. A widget object lives for one
 * request, and inside that request `getItems()` is asked once by the view and
 * possibly once by a widget computing its own scale ({@see
 * BarChartWidget::resolveAutoMax()}). Memoizing would save one call and hide a
 * closure that is not idempotent; the render standard's rule is to make the
 * work cheap, not to cache around it.
 */
trait HasWidgetItems
{
    /** @var array<int, object> */
    protected array $items = [];

    protected ?Closure $itemsCallback = null;

    /**
     * The class every entry in this widget's series has to be.
     *
     * @return class-string
     */
    abstract protected function itemClass(): string;

    /**
     * Set the widget's data series, or a closure resolving it on render.
     *
     * Typed as a loose array on purpose and narrowed here: the entries are
     * runtime input and the point of this method is to reject the wrong ones.
     *
     * @param  array<array-key, mixed>|Closure(?string): array<array-key, mixed>  $items
     *
     * @throws InvalidWidgetDataException When an entry is not an instance of {@see itemClass()}.
     */
    public function items(array|Closure $items): static
    {
        if ($items instanceof Closure) {
            $this->itemsCallback = $items;

            return $this;
        }

        $this->items = $this->validatedItems($items);

        return $this;
    }

    /**
     * The resolved series.
     *
     * @return array<int, object>
     *
     * @throws InvalidWidgetDataException When a closure returned an entry of the wrong class.
     */
    public function getItems(): array
    {
        if ($this->itemsCallback === null) {
            return $this->items;
        }

        return $this->validatedItems(($this->itemsCallback)($this->getActiveFilter()));
    }

    public function hasItems(): bool
    {
        return $this->getItems() !== [];
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return array<int, object>
     *
     * @throws InvalidWidgetDataException
     */
    private function validatedItems(array $items): array
    {
        $expected = $this->itemClass();

        foreach ($items as $item) {
            if (! $item instanceof $expected) {
                throw InvalidWidgetDataException::notItems(static::class, $expected);
            }
        }

        /** @var array<int, object> */
        return array_values($items);
    }

    abstract public function getActiveFilter(): ?string;
}

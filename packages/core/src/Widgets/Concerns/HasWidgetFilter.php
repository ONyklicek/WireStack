<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Widget;

/**
 * A widget narrows what it shows from a small set of named choices.
 *
 * The vocabulary — a key => label map, one of them active — used to live on
 * {@see ChartWidget} alone, which
 * made "last 7 days / this month / this year" a chart feature rather than a
 * widget one. A list of recent records wants the same control, and so does a
 * progress board; three copies of an options map is the shape `CLAUDE.md`
 * forbids, so the semantics moved here and every widget has them.
 *
 * ## Where the selection is applied
 *
 * Nowhere in this trait. The active key is *state*, and a widget is a plain
 * object rebuilt from the declaration on every request — it cannot remember
 * anything across a round trip. The host does:
 * {@see WithWidgets::filterWidget()} records
 * the choice against the widget's key and re-renders that widget alone, and
 * {@see WithWidgets::getVisibleWidgets()}
 * pushes the recorded choice back in before anything reads it. So a filter
 * closure is evaluated on the server, with the selection the user made, on
 * every render — which is what makes `datasets(fn ($filter) => …)` mean
 * anything at all.
 *
 * ## The trap this replaced
 *
 * The chart's filter used to be resolved in the browser: a `<select>` bound to
 * an Alpine property, and an `updateChart()` that assigned `this.labels` and
 * `this.datasets` back onto the chart — the same two values it was constructed
 * with. Changing the selection therefore redrew the identical chart, and the
 * dataset closure was never called with anything but its default. A filter
 * whose options the server enumerates has to be resolved where the data is.
 */
trait HasWidgetFilter
{
    /** @var array<string, string>|null Filter options (key => label) */
    protected ?array $filterOptions = null;

    protected ?string $activeFilter = null;

    /**
     * Add a filter dropdown whose selection drives this widget's closures.
     *
     * @param  array<string, string>  $options  key => label pairs
     * @param  string|null  $default  the key selected before the user chooses — the first option when omitted
     */
    public function filter(array $options, ?string $default = null): static
    {
        $this->filterOptions = $options;
        $this->activeFilter = $default ?? array_key_first($options);

        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getFilterOptions(): ?array
    {
        return $this->filterOptions;
    }

    public function hasFilter(): bool
    {
        return $this->filterOptions !== null;
    }

    /** Set the currently selected filter key. */
    public function activeFilter(?string $filter): static
    {
        $this->activeFilter = $filter;

        return $this;
    }

    public function getActiveFilter(): ?string
    {
        return $this->activeFilter;
    }

    /**
     * Apply a selection that arrived from the host, ignoring one this widget
     * does not offer.
     *
     * The guard is not defensive tidiness: the key and the value both travel in
     * the request, so an edited payload could otherwise put an arbitrary string
     * in front of a `match` inside somebody's dataset closure. A widget answers
     * only for the options it enumerated.
     */
    public function applyFilter(?string $filter): static
    {
        if ($filter === null || ! array_key_exists($filter, $this->filterOptions ?? [])) {
            return $this;
        }

        return $this->activeFilter($filter);
    }

    /**
     * The expression a filter control calls on the host.
     *
     * Null when the widget has no filter or no key to be addressed by — the
     * same rule {@see HasPolling::getPollingDirective()} follows, and for the
     * same reason: without a key there is no region to answer with.
     *
     * @see Widget::key()
     */
    public function getFilterExpression(): ?string
    {
        if (! $this->hasFilter() || $this->getKey() === null) {
            return null;
        }

        return "filterWidget('".$this->getKey()."', \$event.target.value)";
    }

    abstract public function getKey(): ?string;
}

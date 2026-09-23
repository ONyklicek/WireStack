<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

/**
 * A widget that shows the same thing whatever the dashboard's filters say.
 *
 * A dashboard filter narrows every widget at once — "this month", "this
 * customer" — and a widget that cannot be narrowed (the state of an external
 * system, a queue that has no customer) would otherwise sit beside the others
 * looking as if it had been. Saying so is the widget's declaration; the grid
 * then marks it while a filter is narrowing the dashboard, rather than the
 * reader comparing two figures that answer different questions.
 */
trait CanIgnoreDashboardFilters
{
    protected bool $ignoresDashboardFilters = false;

    /** Mark this widget as unaffected by the dashboard's filters. */
    public function ignoresDashboardFilters(bool $ignores = true): static
    {
        $this->ignoresDashboardFilters = $ignores;

        return $this;
    }

    public function isIgnoringDashboardFilters(): bool
    {
        return $this->ignoresDashboardFilters;
    }
}

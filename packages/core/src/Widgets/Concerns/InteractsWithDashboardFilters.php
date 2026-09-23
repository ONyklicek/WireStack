<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use NyonCode\WireCore\Widgets\DashboardFilter;
use NyonCode\WireCore\Widgets\Support\DashboardFilterState;

/**
 * Filters over a whole dashboard: the host's half.
 *
 * The host keeps the selection (in the address), resolves it through
 * {@see DashboardFilterState} and hands it to whatever builds the widgets. The
 * checking and the rules live in that value object and in
 * {@see DashboardFilter}; this is the state and the methods the filter bar
 * calls. Composed into {@see WithWidgets}, and the widgets rebuilt after a
 * change because the list is memoized there.
 */
trait InteractsWithDashboardFilters
{
    /**
     * The dashboard filters' values, as the address carries them.
     *
     * In the query string so a dashboard can be sent as a link exactly as its
     * sender saw it, and a reload keeps what was chosen. Only values that differ
     * from a filter's default are kept, so a dashboard nobody filtered has a
     * clean address. Locked, like the widget state in `WithWidgets`: the browser
     * changes a filter through {@see setDashboardFilter()}, which checks the
     * value against the options, and what the address carried is checked the
     * same way before anything reads it ({@see getDashboardFilterState()}).
     *
     * @var array<string, string>
     */
    #[Locked]
    #[Url(as: 'dashboard', except: [])]
    public array $dashboardFilters = [];

    /** Memoized for the request; forgotten whenever a filter changes. */
    private ?DashboardFilterState $dashboardFilterState = null;

    /**
     * The filters over the whole dashboard.
     *
     * Read by the filter bar and by {@see DashboardFilter()}, which is how
     * `getWidgets()` narrows what it builds.
     *
     * @return array<int, DashboardFilter>
     */
    protected function getDashboardFilters(): array
    {
        return [];
    }

    /**
     * The dashboard filters, resolved against what the address carried.
     *
     * The values are checked against each filter's options here, so nothing
     * downstream ever sees a value the filter did not offer. Memoized for the
     * request: `getWidgets()` asks per widget, and an options closure can be a
     * query.
     */
    public function getDashboardFilterState(): DashboardFilterState
    {
        if ($this->dashboardFilterState instanceof DashboardFilterState) {
            return $this->dashboardFilterState;
        }

        $filters = $this->getDashboardFilters();

        return $this->dashboardFilterState = $filters === []
            ? DashboardFilterState::none()
            : DashboardFilterState::resolve($filters, $this->dashboardFilters);
    }

    /** The value of one dashboard filter — what `getWidgets()` narrows by. */
    public function dashboardFilter(string $name): ?string
    {
        return $this->getDashboardFilterState()->value($name);
    }

    /**
     * Set one dashboard filter — what the filter bar calls.
     *
     * A value the filter does not offer resolves to its default, and only what
     * differs from a default is kept, so the address stays short.
     */
    public function setDashboardFilter(string $name, string $value): void
    {
        $filters = $this->getDashboardFilters();
        $state = DashboardFilterState::resolve($filters, [...$this->dashboardFilters, $name => $value]);

        if (! $state->has($name)) {
            return;
        }

        $this->dashboardFilters = $state->toQuery();
        $this->forgetDashboardFilterState();
    }

    /** Put every dashboard filter back to its default. */
    public function resetDashboardFilters(): void
    {
        $this->dashboardFilters = [];
        $this->forgetDashboardFilterState();
    }

    /** Forget the resolved filters and the widgets built from them. */
    private function forgetDashboardFilterState(): void
    {
        $this->dashboardFilterState = null;
        $this->configuredWidgets = null;
    }
}

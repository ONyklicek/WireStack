<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

class StatsOverviewWidget extends Widget
{
    /** @var array<int, Stat> */
    protected array $stats = [];

    /** @var int Number of columns in the grid (1-4) */
    protected int $columns = 3;

    /**
     * Set the stat cards to display.
     *
     * @param  array<int, Stat>  $stats
     */
    public function stats(array $stats): static
    {
        $this->stats = $stats;

        return $this;
    }

    /**
     * @return array<int, Stat>
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /** Set the number of grid columns for the stat cards (1–4, default 3). */
    public function columns(int $columns): static
    {
        $this->columns = max(1, min(4, $columns));

        return $this;
    }

    public function getGridColumns(): int
    {
        return $this->columns;
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.stats-overview';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'stats' => $this->stats,
            'columns' => $this->columns,
        ];
    }
}

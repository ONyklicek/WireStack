<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Closure;

class ChartWidget extends Widget
{
    protected string $type = 'line';

    /** @var array<int, array<string, mixed>> */
    protected array $datasets = [];

    /** @var array<int, string> */
    protected array $labels = [];

    /** @var array<string, string>|null Filter options (key => label) */
    protected ?array $filterOptions = null;

    protected ?string $activeFilter = null;

    protected ?Closure $datasetsCallback = null;

    protected ?Closure $labelsCallback = null;

    /** @var array<string, mixed> Chart.js options overriding the type defaults. */
    protected array $options = [];

    /**
     * Set the Chart.js chart type.
     *
     * @param  string  $type  line|bar|pie|doughnut
     */
    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the chart datasets, or a closure resolving them from the active filter.
     *
     * @param  array<int, array<string, mixed>>|Closure  $datasets
     */
    public function datasets(array|Closure $datasets): static
    {
        if ($datasets instanceof Closure) {
            $this->datasetsCallback = $datasets;
        } else {
            $this->datasets = $datasets;
        }

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDatasets(): array
    {
        if ($this->datasetsCallback) {
            return ($this->datasetsCallback)($this->activeFilter);
        }

        return $this->datasets;
    }

    /**
     * Set the x-axis labels, or a closure resolving them from the active filter.
     *
     * @param  array<int, string>|Closure  $labels
     */
    public function labels(array|Closure $labels): static
    {
        if ($labels instanceof Closure) {
            $this->labelsCallback = $labels;
        } else {
            $this->labels = $labels;
        }

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getLabels(): array
    {
        if ($this->labelsCallback) {
            return ($this->labelsCallback)($this->activeFilter);
        }

        return $this->labels;
    }

    /**
     * Add a filter dropdown whose selection drives the dataset/label closures.
     *
     * @param  array<string, string>  $options  key => label pairs
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

    public function getActiveFilter(): ?string
    {
        return $this->activeFilter;
    }

    /** Set the currently selected filter key. */
    public function activeFilter(?string $filter): static
    {
        $this->activeFilter = $filter;

        return $this;
    }

    /**
     * Override the Chart.js options. Merged over the type's default options, so
     * callers only specify what they want to change.
     *
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Resolved Chart.js options: the type defaults with the caller's overrides
     * merged on top.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return array_replace_recursive($this->getDefaultOptions(), $this->options);
    }

    /**
     * Baseline Chart.js options shared by all chart types. Type-specific widgets
     * (pie, doughnut, …) override this to add their own defaults.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.chart';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'type' => $this->type,
            'datasets' => $this->getDatasets(),
            'labels' => $this->getLabels(),
            'filterOptions' => $this->filterOptions,
            'activeFilter' => $this->activeFilter,
            'options' => $this->getOptions(),
        ];
    }
}

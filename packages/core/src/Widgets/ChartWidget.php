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

    /**
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
     * @param  array<int, array<string, mixed>>  $datasets
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

    public function activeFilter(?string $filter): static
    {
        $this->activeFilter = $filter;

        return $this;
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
        ];
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Pure-CSS bar chart widget (no JS/Chart.js dependency).
 *
 * Renders a list of {@see ChartItem}s as Tailwind-styled bars in one of three
 * visual modes:
 *
 *  - `vertical` + `finance` — vertical bars with the formatted value above and a
 *    "month / year" style caption below, on a light max-height track.
 *  - `vertical` + `system`  — vertical bars for system metrics (icon, label,
 *    percentage) on a 0–100 % track, with optional grid lines.
 *  - `horizontal` + `system` — horizontal progress bars (label + value rows).
 *
 * This is intentionally separate from {@see ChartWidget}, which is the JS
 * (Chart.js) line/bar/pie/doughnut widget. Both can coexist on a dashboard.
 */
class BarChartWidget extends Widget
{
    use HasColor;

    public const TYPES = ['vertical', 'horizontal'];

    public const VARIANTS = ['finance', 'system', 'default'];

    protected string $type = 'vertical';

    protected string $variant = 'default';

    /** @var array<int, ChartItem> */
    protected array $items = [];

    protected bool $showGrid = false;

    protected bool $showMenu = false;

    /**
     * Absolute scale ceiling. When null the widget is in "percentage mode":
     * each item is scaled either by its own explicit percentage or relative to
     * the largest item value.
     */
    protected ?float $maxValue = null;

    /** Height of the vertical chart plot area, in pixels. */
    protected int $height = 240;

    /** Tailwind rounded scale suffix for the card + bars (e.g. xl, 2xl, lg). */
    protected string $rounded = '2xl';

    /**
     * @param  'vertical'|'horizontal'|string  $type
     */
    public function type(string $type): static
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid chart type [%s]. Allowed: %s.', $type, implode(', ', self::TYPES))
            );
        }

        $this->type = $type;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param  'finance'|'system'|'default'|string  $variant
     */
    public function variant(string $variant): static
    {
        if (! in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid chart variant [%s]. Allowed: %s.', $variant, implode(', ', self::VARIANTS))
            );
        }

        $this->variant = $variant;

        return $this;
    }

    public function getVariant(): string
    {
        return $this->variant;
    }

    /**
     * Set the chart's data series.
     *
     * Validates untrusted runtime input: every entry must be a {@see ChartItem},
     * so the parameter is intentionally typed as a loose array and narrowed here.
     *
     * @param  array<array-key, mixed>  $items
     */
    public function items(array $items): static
    {
        foreach ($items as $item) {
            if (! $item instanceof ChartItem) {
                throw new InvalidArgumentException(
                    'BarChartWidget::items() expects an array of '.ChartItem::class.' instances.'
                );
            }
        }

        $this->items = array_values($items);

        return $this;
    }

    /**
     * @return array<int, ChartItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function showGrid(bool $showGrid = true): static
    {
        $this->showGrid = $showGrid;

        return $this;
    }

    public function shouldShowGrid(): bool
    {
        return $this->showGrid;
    }

    public function showMenu(bool $showMenu = true): static
    {
        $this->showMenu = $showMenu;

        return $this;
    }

    public function shouldShowMenu(): bool
    {
        return $this->showMenu;
    }

    public function maxValue(int|float|null $maxValue): static
    {
        $this->maxValue = $maxValue === null ? null : (float) $maxValue;

        return $this;
    }

    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    public function height(int $height): static
    {
        $this->height = max(1, $height);

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function rounded(string $rounded): static
    {
        $this->rounded = $rounded;

        return $this;
    }

    public function getRounded(): string
    {
        return $this->rounded;
    }

    /**
     * Resolve the 0–100 fill percentage for an item.
     *
     * Precedence: explicit per-item percentage → absolute {@see $maxValue}
     * scaling → automatic scaling relative to the largest item value. The result
     * is always clamped into the [0, 100] range so the view never produces an
     * out-of-bounds bar.
     */
    public function percentageFor(ChartItem $item): float
    {
        if ($item->hasPercentage()) {
            return (float) $item->getPercentage();
        }

        $ceiling = $this->maxValue ?? $this->resolveAutoMax();

        if ($ceiling <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(100.0, $item->getValue() / $ceiling * 100));
    }

    /**
     * Largest item value, used as the scale ceiling in percentage mode. Never
     * returns less than 1 to avoid division by zero on an all-zero data set.
     */
    public function resolveAutoMax(): float
    {
        $values = array_map(static fn (ChartItem $item): float => $item->getValue(), $this->items);

        return max(1.0, ...($values === [] ? [1.0] : $values));
    }

    /**
     * Default color key required by the {@see HasColor} concern. The bar chart
     * has no single color of its own — each {@see ChartItem} carries its own —
     * so this is just the neutral fallback the trait's instance resolvers use.
     */
    public function getColor(): string
    {
        return 'primary';
    }

    /**
     * Safe gradient fill classes for an item, delegating to the canonical
     * {@see HasColor} resolver (allow-list only, no class injection).
     */
    public function fillClassesFor(ChartItem $item): string
    {
        return self::getGradientFillClasses($item->getColor());
    }

    /**
     * Safe accent text classes for an item (labels, values), kept literal so the
     * accent hue matches the item's bar fill (e.g. green fill → green text).
     */
    public function textClassesFor(ChartItem $item): string
    {
        return self::getFillTextClasses($item->getColor());
    }

    protected function viewName(): string
    {
        return 'wire-core::widgets.bar-chart';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'type' => $this->type,
            'variant' => $this->variant,
            'items' => $this->items,
            'showGrid' => $this->showGrid,
            'showMenu' => $this->showMenu,
            'height' => $this->height,
            'rounded' => $this->rounded,
        ];
    }
}

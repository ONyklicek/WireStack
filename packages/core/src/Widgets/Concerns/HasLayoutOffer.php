<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

/**
 * What a widget offers a customisable dashboard: the tray heading it is listed
 * under and the sizes it may be given.
 *
 * Both are declarations about the widget, read by the tray and the edit
 * controls, and neither changes how the widget draws itself — which is why they
 * are one concern apart from the rendering the widget base owns.
 */
trait HasLayoutOffer
{
    protected ?string $group = null;

    /** @var array<int, array{0: int, 1: int}> */
    protected array $sizes = [];

    /**
     * The label each offered size goes by, keyed like {@see $sizes}.
     *
     * @var array<int, string>
     */
    protected array $sizeLabels = [];

    /**
     * Which tray group this widget is offered under.
     *
     * A heading in the list of things a user can put on their dashboard, and
     * nothing else — it does not group anything on the dashboard itself, where
     * the user's own order decides. Null puts the widget in the ungrouped run,
     * which is where a dashboard with a handful of widgets should leave them:
     * one group is a heading over everything, which is a heading that says
     * nothing.
     */
    public function group(?string $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * The sizes this widget may be given, as `[width, height]` pairs — keyed by a label to offer them by name.
     *
     * Empty means the whole grid — 1–4 columns by 1–6 rows, stepped freely.
     * Declaring a list narrows it to sizes the widget actually looks right at:
     * a sparkline row is not a 1×1 tile and a single figure is not a 4×6 one,
     * and letting a user find that out by dragging is worse than not offering
     * it.
     *
     * A string key names the size — `['S' => [1, 1], 'M' => [2, 1], 'L' => [4, 1]]`
     * — and the edit controls then offer those names as buttons instead of
     * width and height steppers. A widget with three sizes is three choices,
     * and a stepper makes the user walk to them one column at a time without
     * saying where the walk ends. Labels are all or nothing: a list with one
     * unnamed pair keeps the steppers, because a button row with a gap in it
     * would have no name to draw.
     *
     * The pairs are the *offer*, not a guarantee about what is stored: a layout
     * that arrives with a size outside them is still clamped to the grid rather
     * than rejected, because a stored layout can outlive the declaration that
     * shaped it.
     *
     * Typed as a loose array and narrowed here, the way `BarChartWidget::items()`
     * is: the pairs are a declaration somebody writes by hand, and half a pair
     * would otherwise reach the grid as a size with no height.
     *
     * @param  array<int|string, mixed>  $sizes
     */
    public function sizes(array $sizes): static
    {
        $pairs = [];
        $labels = [];

        foreach ($sizes as $label => $size) {
            if (! is_array($size) || ! isset($size[0], $size[1]) || ! is_int($size[0]) || ! is_int($size[1])) {
                continue;
            }

            $pairs[] = [$size[0], $size[1]];

            if (is_string($label) && $label !== '') {
                $labels[count($pairs) - 1] = $label;
            }
        }

        $this->sizes = $pairs;
        $this->sizeLabels = count($labels) === count($pairs) ? $labels : [];

        return $this;
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * The name a declared size goes by, or null when the sizes are unnamed.
     *
     * Looked up by the pair rather than by position, because the edit controls
     * ask about a size the layout holds — which may be one the widget declared
     * under a name, or a clamped leftover that has none.
     */
    public function getSizeLabel(int $width, int $height): ?string
    {
        foreach ($this->sizes as $index => $pair) {
            if ($pair === [$width, $height]) {
                return $this->sizeLabels[$index] ?? null;
            }
        }

        return null;
    }

    /** Whether every offered size has a name, so the controls offer names. */
    public function hasNamedSizes(): bool
    {
        return $this->sizeLabels !== [];
    }

    /**
     * The size a widget is added at: the first it offers, or one column by one row.
     *
     * @return array{0: int, 1: int}
     */
    public function getDefaultSize(): array
    {
        return $this->sizes[0] ?? [1, 1];
    }
}

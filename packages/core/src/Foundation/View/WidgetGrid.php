<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColumnSpan;
use NyonCode\WireCore\Widgets\Widget;

/**
 * Standalone widget dashboard grid:
 * `<x-wire::widget-grid :widgets="$this->getVisibleWidgets()" :columns="2" />`.
 *
 * Renders the shared widget-grid view — a responsive grid that lays out each
 * Htmlable widget, honoring its column span and its polling directive.
 *
 * The span is resolved against the grid's own ladder rather than through
 * {@see HasColumnSpan::getColumnSpanClass()}, which answers for a grid that
 * ramps at `sm` and knows no column count. A card grid ramps later, and a span
 * it cannot honour is the one thing that must never reach the page: CSS Grid
 * does not clip a too-wide tile, it invents the column — see
 * `ResponsiveGrid::span()`.
 */
class WidgetGrid extends Component
{
    /**
     * @param  array<int, Widget>  $widgets
     */
    public function __construct(public array $widgets = [], public int $columns = 2) {}

    public function render(): View
    {
        return view('wire-core::widgets.widget-grid');
    }
}

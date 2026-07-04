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
 * Htmlable widget, honoring its column span (via the canonical
 * {@see HasColumnSpan::getColumnSpanClass()})
 * and its polling directive.
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

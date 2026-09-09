<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;
use NyonCode\WireCore\Widgets\Widget;

/** @phpstan-require-extends Component */
trait WithWidgets
{
    use InteractsWithPartials;

    /**
     * @return array<int, Widget>
     */
    abstract protected function getWidgets(): array;

    /**
     * Number of columns in the widget grid (1-4).
     */
    protected function getWidgetColumns(): int
    {
        return 2;
    }

    /**
     * Get only visible widgets.
     *
     * Each one is stamped with a key it can be addressed by across a round trip
     * (see {@see Widget::key()}), derived from its position in `getWidgets()` —
     * the unfiltered list, so that hiding one does not renumber the rest. A
     * widget that was given its own key keeps it.
     *
     * @return array<int, Widget>
     */
    public function getVisibleWidgets(): array
    {
        $visible = [];

        foreach ($this->getWidgets() as $index => $widget) {
            if ($widget->getKey() === null) {
                $widget->key('w'.$index);
            }

            if ($widget->isVisible()) {
                $visible[] = $widget;
            }
        }

        return $visible;
    }

    /**
     * Answer a widget's poll tick with that widget, not with the whole page.
     *
     * A bare `wire:poll` evaluates to `$refresh`, so one polling widget used to
     * re-render every other widget on the dashboard and any table sharing the
     * component — measured at 6.5 ms and 57 kB on a 12-widget grid to deliver
     * one widget's 3.9 kB. The tick now names this method, and the queued
     * partial is the only thing the response carries.
     *
     * Islands cannot do this: an `@island` name is re-evaluated inside its own
     * compiled view file, which never sees the enclosing loop's variable, so
     * `@island($widget->getKey())` throws `Undefined variable $widget` on the
     * first render — the same rule `IslandSemanticsTest` pins for table rows. A
     * partial is chosen by the server and anchored with a plain attribute.
     *
     * Queuing nothing is the safe outcome, not a failure: the coverage rule in
     * `PartialRenderHook` falls back to the full render whenever a call queued
     * no region, so an unknown key, a widget that stopped being visible, or one
     * that stopped polling all end up rendering the page — which is what a
     * changed page shape needs anyway.
     */
    public function refreshWidget(string $key): void
    {
        foreach ($this->getVisibleWidgets() as $widget) {
            if ($widget->getKey() !== $key || ! $widget->isPolling()) {
                continue;
            }

            $this->renderPartial(
                'widget-'.$key,
                fn () => view('wire-core::widgets.widget-cell', ['widget' => $widget])->render(),
            );

            return;
        }
    }
}

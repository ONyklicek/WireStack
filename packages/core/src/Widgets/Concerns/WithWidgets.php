<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Widgets\Widget;

/** @phpstan-require-extends Component */
trait WithWidgets
{
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
     * @return array<int, Widget>
     */
    public function getVisibleWidgets(): array
    {
        return array_values(array_filter(
            $this->getWidgets(),
            fn (Widget $widget) => $widget->isVisible(),
        ));
    }
}

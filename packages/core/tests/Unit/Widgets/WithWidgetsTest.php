<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\CustomWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

// ─── WithWidgets trait ───────────────────────────────────────────────────────

it('returns all visible widgets', function () {
    $page = new class
    {
        use WithWidgets;

        protected function getWidgets(): array
        {
            return [
                StatsOverviewWidget::make()->stats([
                    Stat::make('Revenue', '$100'),
                ]),
                CustomWidget::make()->heading('Visible'),
                CustomWidget::make()->heading('Hidden')->visible(false),
            ];
        }
    };

    expect($page->getVisibleWidgets())->toHaveCount(2);
});

it('returns empty array when no widgets defined', function () {
    $page = new class
    {
        use WithWidgets;

        protected function getWidgets(): array
        {
            return [];
        }
    };

    expect($page->getVisibleWidgets())->toBeEmpty();
});

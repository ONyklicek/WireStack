<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/**
 * A request about ONE widget asks only that widget whether it is visible.
 *
 * `isVisible()` can cost as much as building the widget: a dashboard that hides a
 * widget with nothing to say has to build it to find out. Walking
 * getVisibleWidgets() for a key asked every widget first, so a poll tick for one
 * widget built all of them — measured on a 12-widget application dashboard as
 * 10–18 queries and 40–60 ms per tick, for a widget that needed one or two.
 */
class WlkDashboard extends Component
{
    use WithWidgets;

    /** @var array<int, int> how many times each widget was asked about visibility */
    public static array $asked = [];

    protected function getWidgets(): array
    {
        return array_map(function (int $i) {
            return StatsOverviewWidget::make()
                ->heading('Widget '.$i)
                ->stats([Stat::make('Users', (string) $i)])
                ->filter(['a' => 'A', 'b' => 'B'])
                ->pollingInterval('10s')
                ->visible(function () use ($i): bool {
                    self::$asked[$i] = (self::$asked[$i] ?? 0) + 1;

                    return true;
                });
        }, range(1, 4));
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', [
            'widgets' => $this->getVisibleWidgets(),
            'columns' => 2,
        ]);
    }
}

dataset('požadavky na jeden widget', [
    'poll tick' => [fn ($lw, string $key) => $lw->call('refreshWidget', $key)],
    'lazy load' => [fn ($lw, string $key) => $lw->call('loadWidget', $key)],
    'filter' => [fn ($lw, string $key) => $lw->call('filterWidget', $key, 'b')],
]);

it('asks only the addressed widget whether it is visible', function (Closure $request) {
    $lw = Livewire::test(WlkDashboard::class);
    $second = $lw->instance()->getVisibleWidgets()[1]->getKey();

    WlkDashboard::$asked = [];
    $request($lw, $second);

    expect(array_keys(WlkDashboard::$asked))->toBe([2]);
})->with('požadavky na jeden widget');


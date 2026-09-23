<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Support\DefaultWidgetLayout;
use NyonCode\WireCore\Widgets\Support\WidgetLayout;
use NyonCode\WireCore\Widgets\Support\WidgetSizeOffer;

/**
 * The controls a real application's dashboard had and the framework's did not:
 * a default layout per viewer, autosave, a widget limit, named sizes, a tray
 * that says what a widget shows, and a drop target while dragging.
 *
 * Step 7 of `architecture/plans/customisable-dashboards.md`. The dashboard
 * filters have their own file.
 */
class ControlsDashboard extends Component
{
    use WithWidgets;

    /** @var array<int|string, mixed>|null */
    public ?array $default = null;

    public bool $autosave = false;

    public ?int $max = null;

    protected function widgetLayoutKey(): ?string
    {
        return 'controls';
    }

    protected function defaultWidgetLayout(): ?array
    {
        return $this->default;
    }

    protected function autosavesWidgetLayout(): bool
    {
        return $this->autosave;
    }

    protected function maxWidgets(): ?int
    {
        return $this->max;
    }

    protected function getWidgetColumns(): int
    {
        return 4;
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('kpi')->heading('KPI')
                ->description('Pieces in production and what is waiting.')
                ->sizes(['S' => [1, 1], 'M' => [2, 1], 'L' => [4, 1]])
                ->stats([Stat::make('In production', '3')]),
            StatsOverviewWidget::make()->key('queue')->heading('Queue')->sizes([[2, 1], [2, 2]])
                ->stats([Stat::make('Waiting', '1')]),
            StatsOverviewWidget::make()->key('money')->heading('Money')
                ->stats([Stat::make('Paid', '10')]),
        ];
    }

    public function render()
    {
        return view('controls-dashboard', $this->widgetGridData());
    }
}

function controlsKeys($component): array
{
    return array_map(fn ($widget) => $widget->getKey(), $component->instance()->getVisibleWidgets());
}

beforeEach(function () {
    PreferenceManager::swap(new SessionPreferenceDriver);
    View::addLocation(__DIR__.'/../../Fixtures/views');
});

afterEach(fn () => PreferenceManager::swap(null));

// ─── Default layout ──────────────────────────────────────────────────────────

it('shows only the default layout to somebody who has not arranged anything', function () {
    $component = Livewire::test(ControlsDashboard::class, ['default' => ['queue', 'kpi' => 'L']]);

    expect(controlsKeys($component))->toBe(['queue', 'kpi'])
        ->and($component->instance()->hasStoredWidgetLayout())->toBeFalse();
});

it('puts everything the default leaves out in the tray', function () {
    $component = Livewire::test(ControlsDashboard::class, ['default' => ['kpi']])
        ->call('startEditingWidgets');

    $available = $component->instance()->getAvailableWidgets();

    expect(array_map(fn ($widget) => $widget->getKey(), $available['']))->toBe(['queue', 'money']);
});

it('sizes a default by name, by pair or by what the widget arrives at', function (array $spec, array $expected) {
    $component = Livewire::test(ControlsDashboard::class, ['default' => $spec])->call('startEditingWidgets');

    expect(array_map(
        fn (array $placement): array => [$placement['key'], $placement['w'], $placement['h']],
        $component->get('widgetLayoutDraft'),
    ))->toBe($expected);
})->with([
    'a named size' => [['kpi' => 'M'], [['kpi', 2, 1]]],
    'a pair, snapped to the offer' => [['queue' => [3, 2]], [['queue', 2, 2]]],
    'a bare key arrives at its first size' => [['kpi'], [['kpi', 1, 1]]],
    'a name the widget does not declare' => [['kpi' => 'XL'], [['kpi', 1, 1]]],
    'a key nothing declares' => [['nope', 'money'], [['money', 1, 1]]],
]);

it('lets a stored layout win over the default, and reset come back to it', function () {
    PreferenceManager::resolve()->save('controls', null, WidgetLayout::of([
        ['key' => 'money', 'w' => 1, 'h' => 1],
    ])->toBag());

    $component = Livewire::test(ControlsDashboard::class, ['default' => ['kpi']]);

    expect(controlsKeys($component))->toBe(['money'])
        ->and($component->instance()->hasStoredWidgetLayout())->toBeTrue();

    $component->call('startEditingWidgets')->call('resetWidgetLayout');

    expect(controlsKeys($component))->toBe(['kpi'])
        ->and($component->instance()->hasStoredWidgetLayout())->toBeFalse();
});

it('reads the default through the static resolver the same way', function () {
    $widgets = [StatsOverviewWidget::make()->key('a'), StatsOverviewWidget::make()->key('b')];

    expect(DefaultWidgetLayout::resolve(['b', 'a' => [9, 9]], $widgets, 2)->toBag()['widgets'])->toBe([
        ['key' => 'b', 'w' => 1, 'h' => 1],
        ['key' => 'a', 'w' => 2, 'h' => 6],
    ]);
});

// ─── Autosave ────────────────────────────────────────────────────────────────

it('stores every change at once on an autosaving dashboard', function () {
    Livewire::test(ControlsDashboard::class, ['autosave' => true])
        ->call('startEditingWidgets')
        ->call('removeWidget', 'money');

    // Read from the store, not from the component — nothing was saved by hand.
    expect(array_column(PreferenceManager::resolve()->load('controls', null)['widgets'], 'key'))
        ->toBe(['kpi', 'queue']);
});

it('keeps the draft to itself until Save when it does not autosave', function () {
    Livewire::test(ControlsDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'money');

    expect(PreferenceManager::resolve()->load('controls', null))->toBe([]);
});

it('offers Done instead of Save and Cancel on an autosaving dashboard', function (bool $autosave, array $shown, array $hidden) {
    $html = Livewire::test(ControlsDashboard::class, ['autosave' => $autosave])
        ->call('startEditingWidgets')
        ->html();

    foreach ($shown as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    foreach ($hidden as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
})->with([
    'autosave' => [true, ['widget-layout-done'], ['widget-layout-save', 'widget-layout-cancel']],
    'save by hand' => [false, ['widget-layout-save', 'widget-layout-cancel'], ['widget-layout-done']],
]);

// ─── Widget limit ────────────────────────────────────────────────────────────

it('refuses a widget past the limit and still moves one already placed', function () {
    $component = Livewire::test(ControlsDashboard::class, ['default' => ['kpi', 'queue'], 'max' => 2])
        ->call('startEditingWidgets')
        ->call('placeWidget', 'money', 0)
        ->call('placeWidget', 'queue', 0);

    expect(array_column($component->get('widgetLayoutDraft'), 'key'))->toBe(['queue', 'kpi'])
        ->and($component->instance()->widgetLimitReached())->toBeTrue();
});

it('says in the tray that the dashboard is full, and offers no add buttons', function () {
    $html = Livewire::test(ControlsDashboard::class, ['default' => ['kpi', 'queue'], 'max' => 2])
        ->call('startEditingWidgets')
        ->html();

    expect($html)->toContain('data-testid="widget-tray-limit"')
        ->and($html)->not->toContain('data-testid="widget-add-money"');
});

it('has no limit unless one is declared', function () {
    $component = Livewire::test(ControlsDashboard::class);

    expect($component->instance()->widgetLimitReached())->toBeFalse();
});

// ─── The tray ────────────────────────────────────────────────────────────────

it('says in the tray what a widget shows', function () {
    $html = Livewire::test(ControlsDashboard::class, ['default' => ['queue']])
        ->call('startEditingWidgets')
        ->html();

    expect($html)->toContain('data-testid="widget-tray-description-kpi"')
        ->and($html)->toContain('Pieces in production and what is waiting.');
});

// ─── Named sizes ─────────────────────────────────────────────────────────────

it('keeps a size name with its pair, and drops the names when one is missing', function () {
    $named = StatsOverviewWidget::make()->sizes(['S' => [1, 1], 'L' => [4, 1]]);
    $partly = StatsOverviewWidget::make()->sizes(['S' => [1, 1], [4, 1]]);

    expect($named->hasNamedSizes())->toBeTrue()
        ->and($named->getSizeLabel(4, 1))->toBe('L')
        ->and($named->getSizeLabel(2, 1))->toBeNull()
        ->and($named->getSizes())->toBe([[1, 1], [4, 1]])
        ->and($partly->hasNamedSizes())->toBeFalse()
        ->and($partly->getSizeLabel(1, 1))->toBeNull();
});

it('skips half a pair, and arrives at the first size or one by one', function () {
    $widget = StatsOverviewWidget::make()->sizes([[2], 'x', [2, '1'], [3, 2]]);

    expect($widget->getSizes())->toBe([[3, 2]])
        ->and($widget->getDefaultSize())->toBe([3, 2])
        ->and(StatsOverviewWidget::make()->getDefaultSize())->toBe([1, 1]);
});

it('names each size as it is on this grid', function () {
    $widget = StatsOverviewWidget::make()->sizes(['S' => [1, 1], 'M' => [2, 1], 'L' => [4, 1]]);

    // On two columns M and L are the same size; the first name wins, as the
    // first pair does.
    expect(WidgetSizeOffer::for($widget, 2)->named())->toBe([
        ['label' => 'S', 'width' => 1, 'height' => 1],
        ['label' => 'M', 'width' => 2, 'height' => 1],
    ])->and(WidgetSizeOffer::for(StatsOverviewWidget::make()->sizes([[1, 1]]), 2)->named())->toBe([]);
});

it('offers named sizes as buttons instead of steppers', function () {
    $html = Livewire::test(ControlsDashboard::class)->call('startEditingWidgets')->html();

    expect($html)->toContain('data-testid="widget-size-kpi-M"')
        ->and($html)->not->toContain('data-testid="widget-wider-kpi"')
        // A widget with unnamed sizes keeps its steppers.
        ->and($html)->toContain('data-testid="widget-wider-queue"');
});

// ─── Drop target ─────────────────────────────────────────────────────────────

it('styles the drag ghost as a drop target, and only while editing', function () {
    $component = Livewire::test(ControlsDashboard::class);

    // Escaped in the attribute (`&amp;`), which the browser reads back as `&`.
    expect($component->html())->not->toContain('.sortable-ghost]');

    expect($component->call('startEditingWidgets')->html())->toContain('.sortable-ghost]:outline-dashed');
});

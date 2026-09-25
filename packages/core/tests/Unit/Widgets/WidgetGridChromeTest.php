<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\DashboardFilter;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;

/**
 * What the grid draws AROUND a card — the "not filtered" mark and the edit
 * strip — must neither move the card nor cover it.
 *
 * Both used to: the mark sat in the flow above its card, so the card started a
 * line below its neighbours whenever a filter narrowed, and the edit toolbar was
 * laid over the right end of the card header, on top of the header's own
 * actions. The geometry is proven by `workbench/scripts/verify-dashboard-edit-chrome.mjs`;
 * what is pinned here is the markup that geometry follows from.
 */
class ChromeDashboard extends Component
{
    use WithWidgets;

    protected function widgetLayoutKey(): ?string
    {
        return 'chrome';
    }

    protected function getDashboardFilters(): array
    {
        return [
            DashboardFilter::make('period')->options(['week' => 'Week', 'month' => 'Month'])->default('month'),
        ];
    }

    protected function getWidgets(): array
    {
        return [
            ListWidget::make()->key('orders')->heading('Orders')
                ->headerActions([Action::make('view-all')->label('View all')->url('#orders')])
                ->items([ListItem::make('#1042')]),
            ListWidget::make()->key('server')->heading('Server')->ignoresDashboardFilters()
                ->items([ListItem::make('Uptime')]),
        ];
    }

    public function render()
    {
        return view('controls-dashboard', $this->widgetGridData(2));
    }
}

/**
 * The class lists from an element up to (and including) the grid cell it sits
 * in, innermost first.
 *
 * @return array<int, array<int, string>>
 */
function chromeClassChain(string $html, string $testid): array
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR);

    $node = (new DOMXPath($dom))->query("//*[@data-testid='{$testid}']")->item(0);

    expect($node)->not->toBeNull();

    $chain = [];

    while ($node instanceof DOMElement) {
        $chain[] = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];

        if (str_starts_with($node->getAttribute('wire:key'), 'widget-cell-')) {
            break;
        }

        $node = $node->parentNode;
    }

    return $chain;
}

beforeEach(function () {
    View::addLocation(__DIR__.'/../../Fixtures/views');
    PreferenceManager::swap(new SessionPreferenceDriver);
});
afterEach(fn () => PreferenceManager::swap(null));

it('lays the "not filtered" mark over its cell instead of into the flow above the card', function () {
    $html = Livewire::test(ChromeDashboard::class)->call('setDashboardFilter', 'period', 'week')->html();

    $chain = chromeClassChain($html, 'widget-unfiltered-server');

    // The mark itself takes no room — and the cell it is positioned against is
    // its direct parent, so it sits on that card and not on some ancestor.
    expect($chain[0])->toContain('absolute')
        ->and($chain)->toHaveCount(2)
        ->and(end($chain))->toContain('relative');
});

it('draws the edit strip in the flow above the card rather than over its header', function () {
    $html = Livewire::test(ChromeDashboard::class)->call('startEditingWidgets')->html();

    // Nothing between the handle and the cell is positioned out of the flow —
    // an absolutely placed toolbar is what sat on the header's own actions.
    $positioned = array_filter(
        chromeClassChain($html, 'widget-drag-orders'),
        fn (array $classes): bool => array_intersect($classes, ['absolute', 'fixed']) !== [],
    );

    expect($positioned)->toBe([])
        // …and the header action it used to cover is still drawn in the mode.
        ->and($html)->toContain('data-testid="widget-action-view-all"');
});

it('fills a tall tile with the widget alone, never with the chrome beside it', function () {
    Livewire::test(ChromeDashboard::class)
        ->call('startEditingWidgets')
        ->call('resizeWidget', 'orders', 1, 2)
        ->tap(function ($component) {
            $html = $component->html();

            // Stretching every child of the cell made the edit strip a row tall
            // and gave the mark the cell's whole height.
            expect($html)->toContain('row-span-2')
                ->and($html)->toContain('[&>*>:last-child]:flex-1')
                ->and($html)->not->toContain('[&>*>*]:h-full');
        });
});

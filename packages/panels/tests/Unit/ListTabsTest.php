<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\ListTab;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * Tabs above a list, and widgets above and below any page.
 *
 * What is worth asserting about a tab is that it narrows the list the table
 * actually queries, on top of whatever the resource already scoped it to — and
 * that its count is taken over that scope, not over the whole table.
 */
class LtTicket extends Model
{
    protected $table = 'lt_tickets';

    protected $guarded = [];

    public $timestamps = false;
}

class LtTicketResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return LtTicket::class;
    }

    /** The resource's own scope: archived tickets are never listed. */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('archived', false))
            ->columns([TextColumn::make('subject')]);
    }
}

class LtListTickets extends ListPage
{
    protected static ?string $resource = LtTicketResource::class;

    protected function tabs(): array
    {
        return [
            ListTab::make('all')->showCount(),
            ListTab::make('open')->icon('outline:inbox')->query(fn (Builder $query) => $query->where('status', 'open'))->showCount(),
            ListTab::make('closed')->label('Done')->query(fn (Builder $query) => $query->where('status', 'closed')),
            ListTab::make('flagged')->badge(7)->badgeColor('danger')->query(fn (Builder $query) => $query->where('status', 'flagged')),
        ];
    }

    protected function headerWidgets(): array
    {
        return [StatsOverviewWidget::make()->stats([Stat::make('Open tickets', '2')])];
    }

    protected function footerWidgets(): array
    {
        return [null, StatsOverviewWidget::make()->stats([Stat::make('Closed this week', '1')])];
    }
}

/** A page that writes its own table, with no resource — tabs still hold. */
class LtOwnTable extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(LtTicket::class)->columns([TextColumn::make('subject')]);
    }

    protected function tabs(): array
    {
        return [
            ListTab::make('everything'),
            ListTab::make('open')->query(fn (Builder $query) => $query->where('status', 'open'))->showCount(),
        ];
    }
}

class LtUntabbed extends ListPage
{
    protected static ?string $resource = LtTicketResource::class;
}

class LtBoardWithWidgets extends Page
{
    protected static string $view = 'lt::content';

    protected function headerWidgets(): array
    {
        return [StatsOverviewWidget::make()->stats([Stat::make('Lanes', '3')])];
    }
}

/** A tab's label followed by its count, across Livewire's block markers. */
function tabCount(string $label, int $count): string
{
    return '/'.$label.'<\/span>(?:\s|<!--(?:(?!-->).)*-->)*<span[^>]*>'.$count.'<\/span>/s';
}

beforeEach(function () {
    Schema::create('lt_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('subject');
        $table->string('status');
        $table->boolean('archived')->default(false);
    });

    LtTicket::query()->create(['subject' => 'Printer', 'status' => 'open']);
    LtTicket::query()->create(['subject' => 'Mouse', 'status' => 'open']);
    LtTicket::query()->create(['subject' => 'Screen', 'status' => 'closed']);
    LtTicket::query()->create(['subject' => 'Old open one', 'status' => 'open', 'archived' => true]);

    view()->addNamespace('lt', __DIR__.'/../fixtures/views/list-tabs');
});

it('draws a tab per declaration, the first one active', function () {
    $html = Livewire::test(LtListTickets::class)->html();

    expect($html)->toContain('data-testid="panels-list-tabs"')
        ->and($html)->toContain('data-testid="list-tab-open"')
        ->and($html)->toContain('Done')
        ->and($html)->toMatch('/data-testid="list-tab-all"\s+aria-current="true"/');
});

it('lists every record of the resource scope on the first tab', function () {
    $html = Livewire::test(LtListTickets::class)->html();

    expect($html)->toContain('Printer')->toContain('Screen')
        ->and($html)->not->toContain('Old open one');
});

it('narrows the list on top of the resource scope', function () {
    $html = Livewire::test(LtListTickets::class)->set('activeTab', 'open')->html();

    expect($html)->toContain('Printer')->toContain('Mouse')
        ->and($html)->not->toContain('Screen')
        ->and($html)->not->toContain('Old open one')
        ->and($html)->toMatch('/data-testid="list-tab-open"\s+aria-current="true"/');
});

it('counts a tab over the resource scope, not over the whole table', function () {
    $html = Livewire::test(LtListTickets::class)->html();

    // All: three, not four — the archived one is outside the resource's scope.
    // Open: two, for the same reason.
    expect($html)->toMatch(tabCount('All', 3))
        ->and($html)->toMatch(tabCount('Open', 2));
});

it('shows a badge the tab set itself, and no count on a tab that asked for none', function () {
    $html = Livewire::test(LtListTickets::class)->html();

    expect($html)->toMatch(tabCount('Flagged', 7))
        ->and($html)->not->toMatch('/Done<\/span>(?:\s|<!--(?:(?!-->).)*-->)*<span/s');
});

it('falls back to the first tab for a name nobody declared', function () {
    $html = Livewire::test(LtListTickets::class)->set('activeTab', 'nonsense')->html();

    expect($html)->toContain('Screen')
        ->and($html)->toMatch('/data-testid="list-tab-all"\s+aria-current="true"/');
});

it('keeps the tab in the URL', function () {
    Livewire::withQueryParams(['tab' => 'closed'])
        ->test(LtListTickets::class)
        ->assertSet('activeTab', 'closed')
        ->assertSee('Screen')
        ->assertDontSee('Printer');
});

it('starts a new tab on its first page', function () {
    Livewire::test(LtListTickets::class)
        ->call('gotoPage', 2)
        ->set('activeTab', 'open')
        ->assertSet('paginators.page', 1);
});

it('narrows a table the page wrote itself', function () {
    $html = Livewire::test(LtOwnTable::class)->set('activeTab', 'open')->html();

    // No resource scope here, so the archived open ticket is listed and counted.
    expect($html)->toContain('Old open one')
        ->and($html)->not->toContain('Screen')
        ->and($html)->toMatch(tabCount('Open', 3));
});

it('draws no strip for a list that declares no tabs', function () {
    expect(Livewire::test(LtUntabbed::class)->html())->not->toContain('panels-list-tabs');
});

it('draws widgets above and below the list, skipping a null entry', function () {
    $html = Livewire::test(LtListTickets::class)->html();

    expect(substr_count($html, 'data-testid="page-widgets"'))->toBe(2)
        ->and($html)->toContain('Open tickets')
        ->and($html)->toContain('Closed this week')
        ->and(strpos($html, 'Open tickets'))->toBeLessThan(strpos($html, 'Printer'))
        ->and(strpos($html, 'Closed this week'))->toBeGreaterThan(strpos($html, 'Printer'));
});

it('draws widgets on a page of the application own', function () {
    $html = Livewire::test(LtBoardWithWidgets::class)->html();

    expect($html)->toContain('Lanes')
        ->and(substr_count($html, 'data-testid="page-widgets"'))->toBe(1);
});

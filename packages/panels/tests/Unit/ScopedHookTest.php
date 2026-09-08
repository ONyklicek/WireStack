<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\PageMountingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableComposingPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\WidgetConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\DashboardPage;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * The end of the additive path, rendered.
 *
 * ADR 0029 says an installed module may only add, and that an application
 * adjusts what it ships through a hook rather than by replacing a class. That is
 * only true if a hook can name *one* module's page — which is what a page
 * declaring its registered key is for. Here it is, through a real render:
 * a column added to a list built inside code the test does not own.
 */

class ShInvoice extends Model
{
    protected $table = 'sh_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class ShTask extends Model
{
    protected $table = 'sh_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

/** Stands in for a resource a package ships. */
class ShInvoiceResource implements DescribesResource, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return ShInvoice::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('number')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('number')]);
    }
}

class ShTaskResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return ShTask::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

class ShInvoicesPage extends ListPage
{
    protected static ?string $resource = ShInvoiceResource::class;
}

class ShViewInvoice extends ViewPage
{
    protected static ?string $resource = ShInvoiceResource::class;
}

class ShCreateInvoice extends CreatePage
{
    protected static ?string $resource = ShInvoiceResource::class;
}

final class ShSalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [StatsOverviewWidget::make()->heading('Revenue')->stats([Stat::make('Total', '1.2M')])];
    }
}

class ShSalesPage extends DashboardPage
{
    protected static ?string $dashboard = ShSalesDashboard::class;
}

/** Declares its widgets itself, so it shows nothing registered and has no key. */
class ShStandaloneDashboardPage extends DashboardPage
{
    protected function getWidgets(): array
    {
        return [StatsOverviewWidget::make()->heading('Local')->stats([Stat::make('Rows', '3')])];
    }
}

class ShTasksPage extends ListPage
{
    protected static ?string $resource = ShTaskResource::class;
}

/** A page that shows nothing registered — scoped by class, never by key. */
class ShStandalonePage extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(ShInvoice::class)->columns([TextColumn::make('number')]);
    }
}

beforeEach(function () {
    Schema::create('sh_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('reference')->nullable();
    });

    Schema::create('sh_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('reference')->nullable();
    });

    ShInvoice::create(['number' => 'INV-1', 'reference' => 'from-invoice']);
    ShTask::create(['title' => 'Ship it', 'reference' => 'from-task']);
});

it('reaches one resource page and not the one beside it', function () {
    // `for: 'sh-invoices'` is the key ShInvoiceResource registered under, and the
    // page answers with it because it knows which resource it shows.
    app(PluginManager::class)->hook(
        Hook::TableComposing,
        function (TableComposingPayload $payload): TableComposingPayload {
            $payload->columns = [...$payload->columns, TextColumn::make('reference')];

            return $payload;
        },
        for: 'sh-invoices',
    );

    Livewire::test(ShInvoicesPage::class)->assertSee('from-invoice');
    Livewire::test(ShTasksPage::class)->assertDontSee('from-task');
});

it('leaves a page that shows nothing registered out of a key-scoped hook', function () {
    // Not a gap: a standalone page belongs to no registry entry, so there is
    // nothing for the key to be. It is still addressable by its class.
    app(PluginManager::class)->hook(
        Hook::TableComposing,
        function (TableComposingPayload $payload): TableComposingPayload {
            $payload->columns = [...$payload->columns, TextColumn::make('reference')];

            return $payload;
        },
        for: 'sh-invoices',
    );

    Livewire::test(ShStandalonePage::class)->assertDontSee('from-invoice');
});

it('answers with the resource key it shows, and null when it shows none', function () {
    expect((new ShInvoicesPage)->hookKey())->toBe('sh-invoices')
        ->and((new ShStandalonePage)->hookKey())->toBeNull();
});

// ─── infolist.configuring ────────────────────────────────────────────────────

it('adds an entry to one resource detail page, named by its key', function () {
    app(PluginManager::class)->hook(
        Hook::InfolistConfiguring,
        function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload {
            $payload->schema = [...$payload->schema, TextEntry::make('reference')];

            return $payload;
        },
        for: 'sh-invoices',
    );

    Livewire::test(ShViewInvoice::class, ['record' => 1])->assertSee('from-invoice');
});

it('leaves a detail page a key-scoped hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::InfolistConfiguring,
        function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload {
            $payload->schema = [...$payload->schema, TextEntry::make('reference')];

            return $payload;
        },
        for: 'sh-tasks',
    );

    Livewire::test(ShViewInvoice::class, ['record' => 1])->assertDontSee('from-invoice');
});

// ─── widget.configuring ──────────────────────────────────────────────────────

it('adds a widget to one dashboard page, named by its dashboard key', function () {
    // The key a dashboard registers under, which is why DashboardPage says which
    // dashboard it shows: the widgets are declared inside a class the
    // application may not own.
    app(PluginManager::class)->hook(
        Hook::WidgetConfiguring,
        function (WidgetConfiguringPayload $payload): WidgetConfiguringPayload {
            $payload->widgets[] = StatsOverviewWidget::make()
                ->heading('Added by a plugin')
                ->stats([Stat::make('Rows', '9')]);

            return $payload;
        },
        for: 'sh-sales',
    );

    Livewire::test(ShSalesPage::class)->assertSee('Revenue')->assertSee('Added by a plugin');
    Livewire::test(ShStandaloneDashboardPage::class)->assertDontSee('Added by a plugin');
});

it('answers with the dashboard key it shows, and null when it shows none', function () {
    expect((new ShSalesPage)->hookKey())->toBe('sh-sales')
        ->and((new ShStandaloneDashboardPage)->hookKey())->toBeNull();
});

// ─── page.mounting ───────────────────────────────────────────────────────────

it('seeds one module page state at mount, in a way that survives the round trip', function () {
    // Public, and that word is the whole test: a page mounts once and answers
    // every update after from its snapshot, which carries public properties and
    // nothing else. State a hook wrote anywhere else would be right on the first
    // paint and gone on the second.
    app(PluginManager::class)->hook(
        Hook::PageMounting,
        function (PageMountingPayload $payload): PageMountingPayload {
            $payload->page->data['number'] = 'INV-SEEDED';

            return $payload;
        },
        for: 'sh-invoices',
    );

    Livewire::test(ShCreateInvoice::class)
        ->assertSet('data.number', 'INV-SEEDED')
        ->call('$refresh')
        ->assertSet('data.number', 'INV-SEEDED');
});

it('leaves a page a key-scoped mount hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::PageMounting,
        function (PageMountingPayload $payload): PageMountingPayload {
            $payload->page->data['number'] = 'INV-SEEDED';

            return $payload;
        },
        for: 'sh-tasks',
    );

    Livewire::test(ShCreateInvoice::class)->assertSet('data.number', null);
});

it('runs after the page has mounted, with its record resolved', function () {
    // Livewire calls a component's own mount() before the mount{Trait} hooks, so
    // by the time this fires the view page has its record. A hook dispatched
    // from mount() would see neither that nor a seeded form.
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::PageMounting,
        function (PageMountingPayload $payload) use (&$seen): PageMountingPayload {
            $seen = [$payload->page::class, $payload->title, $payload->page->record, $payload->zone];

            return $payload;
        },
        for: 'sh-invoices',
    );

    Livewire::test(ShViewInvoice::class, ['record' => 1]);

    // The title is readable and not writable: a page's `$title` is protected, so
    // a hook that set it would be offering state the next request throws away.
    expect($seen)->toBe([ShViewInvoice::class, 'Sh Invoice', 1, null]);
});

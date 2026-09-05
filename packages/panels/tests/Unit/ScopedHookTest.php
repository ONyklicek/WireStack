<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Plugin\Hooks\TableComposingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
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
class ShInvoiceResource implements DescribesResource, ProvidesResourceTable
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

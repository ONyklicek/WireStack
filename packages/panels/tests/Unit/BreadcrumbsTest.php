<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * Where a page sits.
 *
 * The menu knows which entry is active and nothing knew that a view page is
 * *inside* the list it came from, so the page says it. Two crumbs is the whole
 * depth these pages have — which is why a list page draws none: a trail of one
 * repeats the heading under it.
 */

class BcInvoice extends Model
{
    protected $table = 'bc_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class BcInvoiceResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function pages(): array
    {
        return [
            'index' => BcListInvoices::class,
            'view' => BcViewInvoice::class,
        ];
    }

    public static function modelClass(): ?string
    {
        return BcInvoice::class;
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('number')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }
}

class BcListInvoices extends ListPage
{
    protected static ?string $resource = BcInvoiceResource::class;
}

class BcViewInvoice extends ViewPage
{
    protected static ?string $resource = BcInvoiceResource::class;

    protected ?string $title = 'Invoice detail';
}

beforeEach(function () {
    Schema::create('bc_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
    });

    BcInvoice::create(['number' => 'INV-1']);

    app(ResourceRegistry::class)->register(BcInvoiceResource::class);

    // A full-page component needs a layout and the framework deliberately does
    // not supply one: without this a routed page answers 500 with "No hint path
    // defined for [layouts]", which reads like a missing view rather than a
    // missing decision.
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');
});

it('draws no trail on a list page', function () {
    // One crumb is the page saying "you are here", which its own heading says.
    Livewire::test(BcListInvoices::class)->assertDontSee('data-testid="breadcrumbs"', escape: false);
});

it('puts the list above a record page', function () {
    $html = Livewire::test(BcViewInvoice::class, ['record' => 1])->html();

    expect($html)->toContain('data-testid="breadcrumbs"')
        ->and($html)->toContain('Bc Invoices')
        ->and($html)->toContain('Invoice detail');
});

it('leaves the list crumb unlinked while nothing routes the resource', function () {
    // The same honesty the menu applies: a resource nothing routes is a crumb
    // you can read and cannot click.
    expect(Livewire::test(BcViewInvoice::class, ['record' => 1])->html())
        ->not->toContain('<a href');
});

it('links the list crumb once the resource is routed', function () {
    Route::middleware('web')->group(fn () => Route::wireResources(only: ['bc-invoices']));

    expect(Livewire::test(BcViewInvoice::class, ['record' => 1])->html())
        ->toContain('href="'.route('wire.bc-invoices.index').'"');
});

it('keeps the trail inside the zone the page was opened in', function () {
    // ADR 0027: during a Livewire update `Route::currentRouteName()` is
    // `livewire.update`, so a trail that re-derived its zone would leave it on
    // every request after the first — right once, wrong forever, and rendering
    // perfectly throughout. The zone is read on mount and carried in the
    // snapshot, which is what this drives through a real request.
    Route::name('business.')->middleware('web')->prefix('business')
        ->group(fn () => Route::wireResources(only: ['bc-invoices']));

    $html = $this->get('/business/bc-invoices/1')->getContent();

    expect($html)->toContain('href="'.route('business.wire.bc-invoices.index').'"')
        ->and($html)->toContain('/business/bc-invoices');
});

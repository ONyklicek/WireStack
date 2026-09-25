<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Resources\Contracts\NestedResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Navigation\RecordPages;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * A resource whose records belong to one record of another.
 *
 * The property that matters is containment: every way into a line — its list,
 * its page by key, its create page — goes through the order it belongs to, so
 * a line of another order is never listed, never opened and never filed under
 * the wrong parent. The URLs, the trail and the parent's tabs follow from the
 * one declaration.
 */
class NrOrder extends Model
{
    protected $table = 'nr_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function lines(): HasMany
    {
        return $this->hasMany(NrLine::class, 'order_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NrLine::class, 'nr_order_tag', 'order_id', 'line_id');
    }
}

class NrLine extends Model
{
    protected $table = 'nr_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class NrOrderResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return NrOrder::class;
    }

    public static function pages(): array
    {
        return ['index' => NrListOrders::class, 'view' => NrViewOrder::class];
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

class NrLineResource implements DescribesResource, NestedResource, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return NrLine::class;
    }

    public static function parentResource(): string
    {
        return NrOrderResource::class;
    }

    public static function parentRelationship(): string
    {
        return 'lines';
    }

    public static function pages(): array
    {
        return [
            'index' => NrListLines::class,
            'create' => NrCreateLine::class,
            'view' => NrViewLine::class,
            'edit' => NrEditLine::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->required()]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('name')]);
    }
}

/** Nested through a relationship that cannot make a child with its key. */
class NrTagResource extends NrLineResource
{
    public static function key(): string
    {
        return 'nr-tags';
    }

    public static function parentRelationship(): string
    {
        return 'tags';
    }

    public static function pages(): array
    {
        return [];
    }
}

/** Nested under a resource that is nested itself. */
class NrDeepResource extends NrLineResource
{
    public static function key(): string
    {
        return 'nr-deep';
    }

    public static function parentResource(): string
    {
        return NrLineResource::class;
    }

    public static function pages(): array
    {
        return ['index' => NrListLines::class];
    }
}

class NrListOrders extends ListPage
{
    protected static ?string $resource = NrOrderResource::class;
}

class NrViewOrder extends ViewPage
{
    protected static ?string $resource = NrOrderResource::class;
}

class NrListLines extends ListPage
{
    protected static ?string $resource = NrLineResource::class;
}

class NrCreateLine extends CreatePage
{
    protected static ?string $resource = NrLineResource::class;
}

class NrViewLine extends ViewPage
{
    protected static ?string $resource = NrLineResource::class;
}

class NrEditLine extends EditPage
{
    protected static ?string $resource = NrLineResource::class;
}

class NrCreateTag extends CreatePage
{
    protected static ?string $resource = NrTagResource::class;
}

beforeEach(function () {
    Schema::create('nr_orders', function (Blueprint $table) {
        $table->id();
        $table->string('number');
    });
    Schema::create('nr_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->nullable();
        $table->string('name');
    });

    NrOrder::query()->create(['number' => 'ORD-1']);
    NrOrder::query()->create(['number' => 'ORD-2']);
    NrLine::query()->create(['order_id' => 1, 'name' => 'Paper']);
    NrLine::query()->create(['order_id' => 1, 'name' => 'Toner']);
    NrLine::query()->create(['order_id' => 2, 'name' => 'Stapler']);

    app(ResourceRegistry::class)->register(NrOrderResource::class);
    app(ResourceRegistry::class)->register(NrLineResource::class);

    Route::middleware('web')->group(fn () => Route::wireResources(only: ['nr-orders', 'nr-lines']));
});

it('routes the nested pages under one record of the parent', function () {
    expect(route('wire.nr-lines.index', ['parent' => 7]))->toBe(url('nr-orders/7/nr-lines'))
        ->and(route('wire.nr-lines.create', ['parent' => 7]))->toBe(url('nr-orders/7/nr-lines/create'))
        ->and(route('wire.nr-lines.edit', ['parent' => 7, 'record' => 3]))->toBe(url('nr-orders/7/nr-lines/3/edit'));
});

it('lists the parent own records only', function () {
    $html = Livewire::test(NrListLines::class, ['parent' => 1])->html();

    expect($html)->toContain('Paper')->toContain('Toner')->not->toContain('Stapler');
});

it('opens a record of the parent, and 404s on a record of another', function () {
    Livewire::test(NrViewLine::class, ['parent' => 1, 'record' => 1])->assertOk()->assertSee('Paper');
    Livewire::test(NrViewLine::class, ['parent' => 1, 'record' => 3])->assertNotFound();
});

it('answers 404 for a parent key that reaches nothing', function () {
    Livewire::test(NrListLines::class, ['parent' => 999])->assertNotFound();
});

it('refuses a nested page mounted without its parent', function () {
    expect($this->refusalMessage(NrListLines::class))->toContain('was mounted without one');
});

it('files a new record under its parent and lands on its page', function () {
    Livewire::test(NrCreateLine::class, ['parent' => 2])
        ->set('data.name', 'Ribbon')
        ->call('save')
        ->assertRedirect(url('nr-orders/2/nr-lines/4'));

    expect(NrLine::query()->find(4)->getAttribute('order_id'))->toBe(2);
});

it('saves an edit through the parent', function () {
    Livewire::test(NrEditLine::class, ['parent' => 1, 'record' => 2])
        ->set('data.name', 'Toner, black')
        ->call('save');

    expect(NrLine::query()->find(2)->getAttribute('name'))->toBe('Toner, black');
});

it('links its own pages with the parent in the URL', function () {
    expect(Livewire::test(NrListLines::class, ['parent' => 1])->html())->toContain('nr-orders/1/nr-lines/create');
});

it('leads the trail through the parent list and record', function () {
    $crumbs = Livewire::test(NrEditLine::class, ['parent' => 1, 'record' => 1])->instance()->breadcrumbs();

    expect(array_map(fn ($crumb) => $crumb->getLabel(), $crumbs))->toBe(['Nr Orders', 'ORD-1', 'Nr Lines', 'Edit Nr Line'])
        ->and($crumbs[1]->getUrl())->toContain('nr-orders/1')
        ->and($crumbs[2]->getUrl())->toContain('nr-orders/1/nr-lines');
});

it('ends a nested list trail on the list itself, unlinked', function () {
    $crumbs = Livewire::test(NrListLines::class, ['parent' => 1])->instance()->breadcrumbs();

    expect(array_map(fn ($crumb) => $crumb->getLabel(), $crumbs))->toBe(['Nr Orders', 'ORD-1', 'Nr Lines'])
        ->and(end($crumbs)->getUrl())->toBeNull();
});

it('gives the parent record a tab to the list of its children', function () {
    $tabs = Livewire::test(NrViewOrder::class, ['record' => 1])->instance()->subNavigation();

    expect(array_keys($tabs))->toBe(['view', 'nr-lines'])
        ->and($tabs['nr-lines']->getUrl())->toContain('nr-orders/1/nr-lines');
});

it('refuses to create through a relationship that cannot make a child', function () {
    app(ResourceRegistry::class)->register(NrTagResource::class);

    try {
        Livewire::test(NrCreateTag::class, ['parent' => 1]);
        $message = '';
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('cannot make a child');
});

it('refuses nesting more than one level deep', function () {
    expect(fn () => Route::wireResource(NrDeepResource::class))->toThrow(ResourceRoutingException::class, 'one level deep');
});

it('takes the parent from the URL when the page is reached by its route', function () {
    View::addLocation(__DIR__.'/../fixtures/views');
    config()->set('livewire.component_layout', 'plain-layout');

    $this->get('/nr-orders/1/nr-lines')->assertOk()->assertSee('Toner')->assertDontSee('Stapler');
    $this->get('/nr-orders/1/nr-lines/1')->assertOk()->assertSee('Paper');
    $this->get('/nr-orders/2/nr-lines/1')->assertNotFound();
});

it('names a parent with nothing to be named by after its label and key', function () {
    NrOrder::query()->whereKey(1)->update(['number' => '']);

    $crumbs = Livewire::test(NrListLines::class, ['parent' => 1])->instance()->breadcrumbs();

    expect($crumbs[1]->getLabel())->toBe('Nr Order 1');
});

it('finds no children for a page with no resource', function () {
    expect(RecordPages::children(null))->toBe([]);
});

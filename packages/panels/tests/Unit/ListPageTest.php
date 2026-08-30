<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * A page that lists one resource.
 *
 * Both ways of declaring it are first class, and the interesting assertions are
 * the two that are neither: a page with no resource and no table(), and a page
 * pointed at a resource that has no list. Both would otherwise render an empty
 * table, which reads as "no records" rather than as a mistake.
 */
class LpOrder extends Model
{
    protected $table = 'lp_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class LpOrderResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return LpOrder::class;
    }

    public function table(Table $table): Table
    {
        return $table->model(LpOrder::class)->columns([TextColumn::make('number')]);
    }
}

/** Identity only — a resource is allowed to have no list. */
class LpReportResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }
}

class LpOrdersPage extends ListPage
{
    protected static ?string $resource = LpOrderResource::class;
}

class LpTitledOrdersPage extends ListPage
{
    protected static ?string $resource = LpOrderResource::class;

    protected ?string $title = 'Open orders';
}

/** The standalone path: no resource at all, table written here. */
class LpStandalonePage extends ListPage
{
    public function table(Table $table): Table
    {
        return $table->model(LpOrder::class)->columns([TextColumn::make('number')]);
    }
}

class LpUndeclaredPage extends ListPage {}

class LpListlessPage extends ListPage
{
    protected static ?string $resource = LpReportResource::class;
}

class LpNotAResourcePage extends ListPage
{
    protected static ?string $resource = LpOrder::class;
}

beforeEach(function () {
    Schema::create('lp_orders', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });

    LpOrder::insert([['number' => 'A-1'], ['number' => 'A-2']]);
});

afterEach(function () {
    Schema::dropIfExists('lp_orders');
});

// ─── The resource path ───────────────────────────────────────────────────────

it('lists the records its resource declares', function () {
    Livewire::test(LpOrdersPage::class)
        ->assertSee('A-1')
        ->assertSee('A-2');
});

it('takes its heading from the resource when none is set', function () {
    // The plural label is on the *static* contract precisely so a page can show
    // it; nothing here composes a table to find out what to call the page.
    expect(LpOrdersPage::resourceClass())->toBe(LpOrderResource::class);

    Livewire::test(LpOrdersPage::class)->assertSee('Lp Orders');
});

it('prefers an explicit heading over the resource label', function () {
    Livewire::test(LpTitledOrdersPage::class)
        ->assertSee('Open orders')
        ->assertDontSee('Lp Orders');
});

// ─── The standalone path ─────────────────────────────────────────────────────

it('works with no resource at all', function () {
    // ADR 0020 keeps both paths first class: a page that writes its own table is
    // an ordinary WithTable host and must not need a resource to exist.
    Livewire::test(LpStandalonePage::class)
        ->assertSee('A-1')
        ->assertSee('A-2');

    expect(LpStandalonePage::resourceClass())->toBeNull();
});

it('has no heading when nothing supplies one', function () {
    expect((new LpStandalonePage)->getTitle())->toBeNull();
});

// ─── Half-declared pages fail loudly ─────────────────────────────────────────

/**
 * The refusal surfaces while the table renders, so Livewire hands it back
 * wrapped in a ViewException. The wrapper keeps the full message, which is what
 * a developer reads, so these assert on that rather than on the class.
 */
function lpRefusal(string $page): string
{
    try {
        Livewire::test($page);
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('refuses a page that declares neither a resource nor a table', function () {
    expect(lpRefusal(LpUndeclaredPage::class))
        ->toContain('has nothing to render')
        ->toContain('both are supported');
});

it('refuses a resource that has no list to give', function () {
    // A resource declares only the surfaces it has, so this is a real
    // possibility rather than a defensive branch.
    expect(lpRefusal(LpListlessPage::class))
        ->toContain(LpReportResource::class)
        ->toContain(ProvidesResourceTable::class);
});

it('refuses a $resource that is not a resource at all', function () {
    expect(lpRefusal(LpNotAResourcePage::class))
        ->toContain(LpOrder::class)
        ->toContain(DescribesResource::class);
});

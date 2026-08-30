<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

/*
 * Putting RelationManager under the owner layer.
 *
 * Nothing about RelationManager changes — it was already a working owner and is
 * still mountable by hand. This only lets a resource say which ones belong to
 * it, so a page embeds them without the application repeating that wiring. The
 * assertion that matters most is therefore the BC one: the direct mount still
 * works, untouched.
 */
class RmInvoice extends Model
{
    protected $table = 'rm_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<RmItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RmItem::class, 'invoice_id');
    }
}

class RmItem extends Model
{
    protected $table = 'rm_items';

    protected $guarded = [];

    public $timestamps = false;
}

class RmItemsManager extends RelationManager
{
    protected string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('product')]);
    }
}

class RmNotAManager {}

class RmInvoiceResource implements DescribesResource, ProvidesRelationManagers, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RmInvoice::class;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }

    public function relationManagers(): array
    {
        return [RmItemsManager::class];
    }
}

class RmBadResource implements DescribesResource, ProvidesRelationManagers, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RmInvoice::class;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }

    public function relationManagers(): array
    {
        return [RmNotAManager::class, RmItemsManager::class];
    }
}

/** Declares no related lists — an ordinary thing for a record to be. */
class RmPlainResource implements DescribesResource, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return RmInvoice::class;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }
}

class RmViewInvoice extends ViewPage
{
    protected static ?string $resource = RmInvoiceResource::class;
}

class RmViewBad extends ViewPage
{
    protected static ?string $resource = RmBadResource::class;
}

class RmViewPlain extends ViewPage
{
    protected static ?string $resource = RmPlainResource::class;
}

beforeEach(function () {
    Schema::create('rm_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });
    Schema::create('rm_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->string('product');
    });

    RmInvoice::insert([['id' => 1, 'number' => 'INV-1']]);
    RmItem::insert([['invoice_id' => 1, 'product' => 'Bolt']]);
});

afterEach(function () {
    Schema::dropIfExists('rm_items');
    Schema::dropIfExists('rm_invoices');
});

// ─── The BC promise ──────────────────────────────────────────────────────────

it('still mounts a relation manager directly, scoped to its owner', function () {
    // The pre-existing usage, untouched by the owner layer.
    Livewire::test(RmItemsManager::class, ['ownerRecord' => RmInvoice::find(1)])
        ->assertSee('Bolt');
});

// ─── What the layer adds ─────────────────────────────────────────────────────

it('embeds the managers its resource declares', function () {
    Livewire::test(RmViewInvoice::class, ['record' => 1])
        ->assertSee('INV-1')
        ->assertSee('Bolt');
});

it('lists what a page will embed', function () {
    expect((new RmViewInvoice)->relationManagers())->toBe([RmItemsManager::class]);
});

it('embeds none where the resource declares none', function () {
    // Unlike a missing table or form this is not an error: having no related
    // lists is ordinary, so the page renders and simply shows none.
    Livewire::test(RmViewPlain::class, ['record' => 1])->assertSee('INV-1');

    expect((new RmViewPlain)->relationManagers())->toBe([]);
});

it('drops a declared class that is not a relation manager', function () {
    // It would otherwise fail deep inside Livewire's mount with a message about
    // the wrong component; the page renders what it can.
    expect((new RmViewBad)->relationManagers())->toBe([RmItemsManager::class]);

    Livewire::test(RmViewBad::class, ['record' => 1])->assertSee('Bolt');
});

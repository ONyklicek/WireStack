<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WirePanels\Resources\Pages\ViewPage;

/*
 * Showing one record, read-only.
 *
 * ADR 0020 asked whether a view page needs an owner concern of its own; it does
 * not (Q2), so the page is a renderer of an Infolist and composes no host trait
 * at all — there is no state to bind and nothing to submit.
 */
class VpOrder extends Model
{
    protected $table = 'vp_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class VpOrderResource implements DescribesResource, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return VpOrder::class;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('number'),
            TextEntry::make('customer'),
        ]);
    }
}

class VpListOnlyResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return VpOrder::class;
    }
}

class VpViewOrder extends ViewPage
{
    protected static ?string $resource = VpOrderResource::class;
}

class VpTitledView extends ViewPage
{
    protected static ?string $resource = VpOrderResource::class;

    protected ?string $title = 'Order detail';
}

class VpViewFormless extends ViewPage
{
    protected static ?string $resource = VpListOnlyResource::class;
}

beforeEach(function () {
    Schema::create('vp_orders', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->string('customer');
    });

    VpOrder::insert([['id' => 1, 'number' => 'A-1', 'customer' => 'Acme']]);
});

afterEach(function () {
    Schema::dropIfExists('vp_orders');
});

it('shows the record the resource describes', function () {
    Livewire::test(VpViewOrder::class, ['record' => 1])
        ->assertSee('A-1')
        ->assertSee('Acme');
});

it('titles itself from the resource, in the singular', function () {
    expect((new VpViewOrder)->getTitle())->toBe('Vp Order')
        ->and((new VpTitledView)->getTitle())->toBe('Order detail');
});

it('resolves the record from a key, not from a stored model', function () {
    // The key is what travels in the Livewire snapshot; the record is looked up
    // per request, so a write by someone else is visible on the next round trip
    // rather than being frozen at mount.
    $page = Livewire::test(VpViewOrder::class, ['record' => 1]);

    VpOrder::find(1)->update(['customer' => 'Globex']);

    expect($page->call('$refresh')->html())->toContain('Globex');
});

it('accepts a model handed to it directly', function () {
    Livewire::test(VpViewOrder::class, ['record' => VpOrder::find(1)])
        ->assertSee('A-1');
});

function vpRefusal(string $page, array $params = []): string
{
    try {
        Livewire::test($page, $params);
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('refuses a resource with no read-only surface', function () {
    expect(vpRefusal(VpViewFormless::class, ['record' => 1]))
        ->toContain(VpListOnlyResource::class)
        ->toContain(ProvidesResourceInfolist::class);
});

it('refuses to render without a record', function () {
    expect(vpRefusal(VpViewOrder::class))->toContain('mounted without a record');
});

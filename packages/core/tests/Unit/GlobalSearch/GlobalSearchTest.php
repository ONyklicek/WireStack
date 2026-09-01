<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearch;
use NyonCode\WireCore\GlobalSearch\GlobalSearchPalette;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;

class GsOrder extends Model
{
    protected $table = 'gs_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class GsCustomer extends Model
{
    protected $table = 'gs_customers';

    protected $guarded = [];

    public $timestamps = false;
}

class GsOrderResource implements DescribesResource, GloballySearchable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return GsOrder::class;
    }

    public static function globallySearchableAttributes(): array
    {
        return ['reference', 'status'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: static::key(),
            recordKey: $record->getKey(),
            title: $record->reference,
            subtitle: $record->status,
            url: '/orders/'.$record->getKey(),
            icon: 'outline:shopping-cart',
        );
    }
}

/** Registered and routable, but deliberately not searchable. */
class GsCustomerResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return GsCustomer::class;
    }
}

/** Opted in with no model, which V2.0 allows and this query cannot serve. */
class GsModellessResource implements DescribesResource, GloballySearchable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function globallySearchableAttributes(): array
    {
        return ['anything'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult('modelless', 1, 'never');
    }
}

function gsSearch(array $resources = [GsOrderResource::class]): GlobalSearch
{
    $registry = new ResourceRegistry;
    $registry->registerMany($resources);

    return new GlobalSearch($registry);
}

beforeEach(function () {
    Schema::create('gs_orders', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
        $table->string('status');
    });
    Schema::create('gs_customers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    GsOrder::create(['reference' => 'INV-1001', 'status' => 'paid']);
    GsOrder::create(['reference' => 'INV-1002', 'status' => 'overdue']);
    GsOrder::create(['reference' => 'REF-2001', 'status' => 'paid']);
    GsCustomer::create(['name' => 'INV Holdings']);
});

afterEach(function () {
    Schema::dropIfExists('gs_orders');
    Schema::dropIfExists('gs_customers');
});

it('finds records across a registered resource and shapes them for the palette', function () {
    $results = gsSearch()->search('INV-100');

    expect($results)->toHaveKey('gs-orders')
        ->and($results['gs-orders'])->toHaveCount(2)
        ->and($results['gs-orders'][0])->toBeInstanceOf(GlobalSearchResult::class)
        ->and($results['gs-orders'][0]->title)->toBe('INV-1001')
        ->and($results['gs-orders'][0]->subtitle)->toBe('paid')
        ->and($results['gs-orders'][0]->url)->toBe('/orders/1')
        ->and($results['gs-orders'][0]->resourceKey)->toBe('gs-orders');
});

it('matches any of the declared attributes, not just the first', function () {
    $results = gsSearch()->search('overdue');

    expect($results['gs-orders'])->toHaveCount(1)
        ->and($results['gs-orders'][0]->title)->toBe('INV-1002');
});

it('leaves a resource that never opted in out of the search entirely', function () {
    // GsCustomer has a row matching "INV", and the palette must not find it:
    // the resource does not implement GloballySearchable, which is how a
    // resource says "not searchable" without a method it was forced to have.
    $results = gsSearch([GsOrderResource::class, GsCustomerResource::class])->search('INV');

    expect(array_keys($results))->toBe(['gs-orders']);
});

it('returns nothing for an empty term rather than the whole database', function () {
    expect(gsSearch()->search(''))->toBe([])
        ->and(gsSearch()->search('   '))->toBe([]);
});

it('leaves out a resource that matched nothing instead of mapping it to an empty list', function () {
    // So a caller can render group headings by iterating the result.
    expect(gsSearch()->search('nothing-matches-this'))->toBe([]);
});

it('escapes LIKE wildcards in the term', function () {
    // Without escaping, "INV-100%" is "starts with INV-100" and matches two
    // rows; a user who typed a literal percent gets results they did not ask
    // for, and "a_b" would match "axb".
    GsOrder::create(['reference' => 'INV-100%', 'status' => 'paid']);

    $results = gsSearch()->search('INV-100%');

    expect($results['gs-orders'])->toHaveCount(1)
        ->and($results['gs-orders'][0]->title)->toBe('INV-100%');
});

it('caps how many rows one resource contributes', function () {
    foreach (range(1, 20) as $i) {
        GsOrder::create(['reference' => 'BULK-'.$i, 'status' => 'paid']);
    }

    expect(gsSearch()->search('BULK'))->toHaveKey('gs-orders')
        ->and(gsSearch()->search('BULK')['gs-orders'])->toHaveCount(GlobalSearch::PER_RESOURCE_LIMIT)
        ->and(gsSearch()->search('BULK', 3)['gs-orders'])->toHaveCount(3);
});

it('serves nothing for a searchable resource with no model behind it', function () {
    // V2.0 allows a resource over a non-Eloquent source. It is registered and
    // routed like any other; this query simply has nothing to run against, and
    // must say so by returning nothing rather than by throwing.
    expect(gsSearch([GsModellessResource::class])->search('anything'))->toBe([]);
});

it('hides a record the user may not view', function () {
    // A term can match something forbidden, and listing its title has leaked it
    // whether or not the click is refused afterwards.
    Gate::policy(GsOrder::class, GsOrderPolicy::class);
    $this->actingAs(new GsUser);

    $results = gsSearch()->search('INV-100');

    expect($results['gs-orders'])->toHaveCount(1)
        ->and($results['gs-orders'][0]->title)->toBe('INV-1001');
});

it('shows a guest nothing from a model that has a policy', function () {
    // Measured rather than assumed: with a policy registered and nobody logged
    // in, Gate has no user to hand the policy and answers no. That is the right
    // way round — a palette that listed guarded records to a guest because the
    // check could not run is the failure that matters — so it is pinned here
    // instead of being rediscovered as a surprise.
    Gate::policy(GsOrder::class, GsOrderPolicy::class);

    expect(gsSearch()->search('INV-100'))->toBe([]);
});

it('falls open when a model has no policy at all', function () {
    // Laravel's own answer for an unguarded model, and what keeps the palette
    // usable in an app that authorizes nowhere.
    expect(gsSearch()->search('INV-100')['gs-orders'])->toHaveCount(2);
});

class GsUser extends User
{
    protected $table = 'gs_users';

    protected $guarded = [];

    public $timestamps = false;

    protected $attributes = ['id' => 1];
}

class GsOrderPolicy
{
    public function view($user, GsOrder $order): bool
    {
        return $order->status !== 'overdue';
    }
}

// ─── The palette component ───────────────────────────────────────

it('renders the results it was asked for', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->assertSee('INV-1001')
        ->assertSee('INV-1002')
        ->assertDontSee('REF-2001');
});

it('says nothing has been typed yet before it says nothing matched', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->assertSee(__('wire-core::global-search.prompt'))
        ->set('term', 'zzz-no-such-thing')
        ->assertSee(__('wire-core::global-search.empty'));
});

it('walks the arrow keys through every group as one list', function () {
    // Flat rather than (group, row): pressing Down on the last row of one group
    // has to reach the first row of the next, not nothing.
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->assertSet('active', 0)
        ->call('moveDown')->assertSet('active', 1)
        // Wraps, so Down at the end lands back at the top rather than stalling.
        ->call('moveDown')->assertSet('active', 0)
        ->call('moveUp')->assertSet('active', 1);
});

it('moves nowhere when there is nothing to move through', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->call('moveDown')->assertSet('active', 0)
        ->call('moveUp')->assertSet('active', 0);
});

it('puts the cursor back to the top when the term changes', function () {
    // Otherwise one more character while sitting on row two keeps the cursor on
    // row two of a different result set, and Enter opens something the user
    // never looked at.
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->call('moveDown')
        ->assertSet('active', 1)
        ->set('term', 'INV-1002')
        ->assertSet('active', 0);
});

it('navigates to the active result and closes', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('open', true)
        ->set('term', 'INV-100')
        ->call('moveDown')
        ->call('select')
        ->assertRedirect('/orders/2')
        ->assertSet('open', false);
});

it('does nothing on select when the active row has nowhere to go', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsUrllessResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('open', true)
        ->set('term', 'INV-100')
        ->call('select')
        ->assertNoRedirect()
        // Still open: closing on a click that went nowhere would look like the
        // palette had answered.
        ->assertSet('open', true);
});

it('clears the term when it closes, so it opens empty next time', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->call('open')->assertSet('open', true)
        ->set('term', 'INV')
        ->call('moveDown')
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('term', '')
        ->assertSet('active', 0);
});

/** A resource whose records have no page to open. */
class GsUrllessResource implements DescribesResource, GloballySearchable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return GsOrder::class;
    }

    public static function globallySearchableAttributes(): array
    {
        return ['reference'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult('gs-urlless', $record->getKey(), $record->reference);
    }
}

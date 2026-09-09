<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Plugin\Hooks\SearchQueryingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Contracts\ClassifiesComponentActions;
use NyonCode\WireCore\Foundation\Contracts\ProvidesCommands;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\UnroutedPageUrls;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearch;
use NyonCode\WireCore\GlobalSearch\GlobalSearchPalette;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;
use NyonCode\WireCore\GlobalSearch\PaletteCommands;
use NyonCode\WireCore\GlobalSearch\PaletteNavigation;
use NyonCode\WireCore\GlobalSearch\PaletteRowKind;

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

function gsSearch(array $resources = [GsOrderResource::class], ?ResolvesPageUrls $urls = null): GlobalSearch
{
    $registry = new ResourceRegistry;
    $registry->registerMany($resources);

    return $urls === null
        ? new GlobalSearch(new Catalog([$registry]))
        : new GlobalSearch(new Catalog([$registry]), $urls);
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

/** An application that needs more than "LIKE over these columns". */
class GsWideningSearch extends GlobalSearch
{
    protected function searchResource(string $resource, string $term, int $perResource): array
    {
        return [new GlobalSearchResult($resource::key(), 0, 'widened: '.$term)];
    }
}

it('searches once per render, not once per thing that asks for the results', function () {
    // `$this->results` is a cached computed property; `getResultsProperty()` is
    // the method behind it and caches nothing. Every caller that reaches for the
    // method instead of the property pays for the whole search again — one query
    // per opted-in resource, per caller, per keystroke.
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(GlobalSearchPalette::class)->set('term', 'INV-100');

    $searches = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'gs_orders'))
        ->count();

    DB::disableQueryLog();

    expect($searches)->toBe(1);
});

it('searches through whatever the container says a search is', function () {
    // `searchResource()` and `matchAny()` are protected because an application
    // is meant to override them — a resource whose match needs a join, a
    // full-text index, or a search service. Building the searcher with `new`
    // here would make both unreachable from the one surface that renders them,
    // and the override would silently never run.
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));
    app()->bind(GlobalSearch::class, GsWideningSearch::class);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'anything')
        ->assertSee('widened: anything');
});

/** Results under a key nothing in the catalogue stands behind. */
class GsStrayKeySearch extends GlobalSearch
{
    public function search(string $term, int $perResource = self::PER_RESOURCE_LIMIT, ?string $zone = null): array
    {
        return ['gs-stray' => [new GlobalSearchResult('gs-stray', 1, 'orphan row')]];
    }
}

it('keeps the raw key as a heading when nothing in the catalogue answers for it', function () {
    // `pluralLabel()` is the plural human name and the key is an identifier, so
    // the heading is the label whenever there is one. A catalogue emptied
    // between the search and the render leaves neither — and a heading is not
    // worth a crash, so the key stands in.
    app()->bind(GlobalSearch::class, GsStrayKeySearch::class);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'anything')
        ->assertSee('gs-stray')
        ->assertSee('orphan row');
});

it('renders the results it was asked for', function () {
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->assertSee('INV-1001')
        ->assertSee('INV-1002')
        ->assertDontSee('REF-2001');
});

it('heads each group with the resource\'s plural label, not its key', function () {
    // `pluralLabel()` owns the plural human name of a resource; the key is what
    // routes and configures it. A heading built from the key is a second
    // vocabulary for the same word — and reads as the identifier it is the
    // moment a resource makes the two differ.
    app()->instance(ResourceRegistry::class, tap(new ResourceRegistry)->register(GsOrderResource::class));

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->assertSee(GsOrderResource::pluralLabel())
        ->assertDontSee('>'.GsOrderResource::key().'<', escape: false);
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

it('points a result at the record page, without the resource writing a path', function () {
    // The defect ADR 0026 was written from: a resource carries the two halves of
    // the URL already — its key and the record's key — so hand-writing the path
    // is copying what the router knows. Both literals this repository's own
    // workbench carried were wrong: one pointed at a preview shell, the other at
    // a page with no record in it, and nothing failed.
    DB::table('gs_orders')->insert(['reference' => 'INV-9', 'status' => 'open']);

    $urls = new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return '/admin/'.$key.'/'.$page.'/'.$parameters['record'];
        }
    };

    $results = gsSearch([GsUrllessResource::class], $urls)->search('INV-9');
    $record = DB::table('gs_orders')->where('reference', 'INV-9')->first();

    // Keyed by the catalogue's key, which is what every other surface addresses
    // this by — not by whatever the result claims for itself.
    expect($results['gs-orders'][0]->url)->toBe('/admin/gs-orders/view/'.$record->id);
});

it('leaves a result that named its own url alone', function () {
    // An explicit URL always wins: a resource pointing somewhere outside the
    // convention is a decision, not an omission.
    DB::table('gs_orders')->insert(['reference' => 'INV-8', 'status' => 'open']);

    $urls = new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return '/should-not-win';
        }
    };

    $results = gsSearch([GsOrderResource::class], $urls)->search('INV-8');

    expect($results['gs-orders'][0]->url)->not->toBe('/should-not-win');
});

/** Registered, searchable by declaration, and with no records to search. */
class GsRecordlessThing implements GloballySearchable
{
    public static function key(): string
    {
        return 'gs-recordless';
    }

    public static function globallySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult('gs-recordless', 1, 'never');
    }
}

it('skips a searchable thing that has no records to search', function () {
    // The catalogue holds dashboards too, and a dashboard has no model. Before
    // ADR 0026 this list came from the resource registry and the question could
    // not arise; now searchability is an opt-in anything may declare, so having
    // something to search has to be checked rather than assumed.
    $source = new class implements RegistrySource
    {
        public function registeredClasses(): array
        {
            return ['gs-recordless' => GsRecordlessThing::class];
        }
    };

    expect((new GlobalSearch(new Catalog([$source])))->search('never'))->toBe([]);
});

it('leaves a result unlinked when nothing owns routing', function () {
    DB::table('gs_orders')->insert(['reference' => 'INV-7', 'status' => 'open']);

    expect(gsSearch([GsUrllessResource::class])->search('INV-7')['gs-orders'][0]->url)->toBeNull();
});

// ─── search.querying ─────────────────────────────────────────────────────────

/*
 * The palette, narrowable by something that did not write the resource.
 *
 * ADR 0030 §6 held this back until something asked for it. What asks is the same
 * thing every other hook here answers to: a module ships a searchable resource,
 * and the application that installed it wants its own rule over the rows —
 * archived records kept out of the palette, a scope the module never declared.
 */

it('lets a hook narrow one resource query before it runs', function () {
    app(PluginManager::class)->hook(
        Hook::SearchQuerying,
        function (SearchQueryingPayload $payload): SearchQueryingPayload {
            $payload->query->where('status', 'paid');

            return $payload;
        },
    );

    $results = gsSearch()->search('INV-100');

    expect($results['gs-orders'])->toHaveCount(1)
        ->and($results['gs-orders'][0]->title)->toBe('INV-1001');
});

it('hands the hook the term and the resource it is searching', function () {
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::SearchQuerying,
        function (SearchQueryingPayload $payload) use (&$seen): SearchQueryingPayload {
            $seen = [$payload->term, $payload->resource];

            return $payload;
        },
    );

    gsSearch()->search('  INV-100  ');

    // Trimmed, because that is the term the query was built from — a callback
    // told otherwise would filter on something the palette never searched for.
    expect($seen)->toBe(['INV-100', GsOrderResource::class]);
});

it('scopes a search hook by the catalogue key and by the model', function (string $scope) {
    // The catalogue's key, not the class's own: the two are the same only by
    // agreement, and it is the catalogue's that every other surface addresses a
    // resource by.
    app(PluginManager::class)->hook(
        Hook::SearchQuerying,
        function (SearchQueryingPayload $payload): SearchQueryingPayload {
            $payload->query->where('status', 'nothing-is-this');

            return $payload;
        },
        for: $scope,
    );

    expect(gsSearch()->search('INV-100'))->toBe([]);
})->with([
    'the catalogue key' => 'gs-orders',
    'the model' => GsOrder::class,
]);

it('leaves a resource a scoped hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::SearchQuerying,
        function (SearchQueryingPayload $payload): SearchQueryingPayload {
            $payload->query->where('status', 'nothing-is-this');

            return $payload;
        },
        for: 'some-other-resource',
    );

    expect(gsSearch()->search('INV-100')['gs-orders'])->toHaveCount(2);
});

it('runs before authorization, so a hook cannot widen past a policy', function () {
    // The hook holds the builder; the policy runs per record afterwards. A
    // callback that removed every `where` would still not list a row the user
    // may not open — which is the property the dispatch site was placed for.
    Gate::policy(GsOrder::class, GsOrderPolicy::class);
    $this->actingAs(new GsUser);

    app(PluginManager::class)->hook(
        Hook::SearchQuerying,
        function (SearchQueryingPayload $payload): SearchQueryingPayload {
            // Every constraint dropped, the term included.
            $payload->query = GsOrder::query()->limit(10);

            return $payload;
        },
    );

    $results = gsSearch()->search('INV-100');

    // Three rows come back from the widened query and the policy drops the
    // overdue one — so the hook reached the builder and never reached the check.
    expect($results['gs-orders'])->toHaveCount(2)
        ->and(array_map(fn ($r) => $r->title, $results['gs-orders']))->toBe(['INV-1001', 'REF-2001']);
});

// ─── The palette's other three row kinds ───────────────────────────────────────
//
// Records were the whole palette until now. Navigation entries, standalone
// commands and a record's own actions are the other three, and each carries a
// rule the record path does not: the menu is filtered by `isVisible()` rather
// than a policy, a command is filtered by `canExecute()`, and an action that has
// to ask something must never be run by a surface that cannot ask.

class GsCommandResource implements DescribesResource, GloballySearchable, ProvidesCommands, ProvidesNavigation
{
    use DescribesRecords;

    /** Set by the runnable command, so a test can see it actually ran. */
    public static mixed $ran = null;

    public static function modelClass(): ?string
    {
        return GsOrder::class;
    }

    /**
     * Named rather than derived. `DescribesRecords` builds the key from the
     * *model*, so sharing `GsOrder` with `GsOrderResource` would give both the
     * key `gs-orders` — and the catalogue refuses two classes under one key.
     */
    public static function key(): string
    {
        return 'gs-commands';
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Sales Orders')->icon('outline:banknotes');
    }

    public static function globallySearchableAttributes(): array
    {
        return ['reference'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: static::key(),
            recordKey: $record->getKey(),
            title: $record->reference,
            url: '/commands/'.$record->getKey(),
        );
    }

    public static function commands(?object $record = null): array
    {
        if ($record !== null) {
            return [
                Action::make('cancel')->label('Cancel order')->requiresConfirmation(),
                Action::make('touch')->label('Touch order')->action(function () use ($record) {
                    static::$ran = $record->getKey();
                }),
                Action::make('forbidden')->label('Forbidden order thing')->hidden(),
            ];
        }

        return [
            Action::make('recount')->label('Recount stock')->action(function () {
                static::$ran = 'recount';
            }),
            Action::make('export')->label('Export orders')->action(function () {
                static::$ran = 'export';
            }),
            Action::make('import')->label('Import records')->requiresConfirmation(),
            Action::make('secret')->label('Recount secrets')->hidden(),
        ];
    }
}

/** Registered, in the menu, and offering nothing — the control. */
class GsHiddenNavResource implements DescribesResource, ProvidesNavigation
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Hidden Orders')->hidden();
    }
}

function gsRegister(array $resources): void
{
    $registry = new ResourceRegistry;
    $registry->registerMany($resources);

    app()->instance(ResourceRegistry::class, $registry);
}

/** Routes every key, so navigation and record pages have somewhere to point. */
function gsRouted(): void
{
    app()->instance(ResolvesPageUrls::class, new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            $suffix = isset($parameters['record']) ? '/'.$parameters['record'] : '';

            return '/'.($zone === null ? '' : $zone.'/').$key.$suffix;
        }
    });
}

beforeEach(function () {
    GsCommandResource::$ran = null;
});

it('offers menu entries whose label matches, as their own group', function () {
    gsRegister([GsCommandResource::class]);
    gsRouted();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'Sales')
        ->assertSee('Sales Orders')
        ->assertSee(__('wire-core::global-search.navigation'));
});

it('matches a menu entry on its label and never on its registry key', function () {
    // The key is an identifier the user has never been shown. Matching it would
    // surface a row for a string that appears nowhere on screen.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    $rows = app(PaletteNavigation::class)->search('gs-command');

    expect($rows)->toBe([]);
});

it('does not offer a menu entry the menu itself hides', function () {
    // Visibility here is `isVisible()`, not a policy over a record — the palette
    // must not re-answer a question the menu has already answered.
    gsRegister([GsCommandResource::class, GsHiddenNavResource::class]);
    gsRouted();

    $rows = app(PaletteNavigation::class)->search('Orders');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->title)->toBe('Sales Orders');
});

it('drops a menu entry this zone cannot reach', function () {
    // A menu draws a URL-less entry perfectly well; a palette cannot, because
    // every row in it is something Enter does.
    gsRegister([GsCommandResource::class]);

    // No ResolvesPageUrls binding, so nothing routes anything.
    app()->instance(ResolvesPageUrls::class, new UnroutedPageUrls);

    expect(app(PaletteNavigation::class)->search('Sales'))->toBe([]);
});

it('points a menu row into the zone the palette was opened in', function () {
    gsRegister([GsCommandResource::class]);
    gsRouted();

    $rows = app(PaletteNavigation::class)->search('Sales', 'admin');

    expect($rows[0]->url)->toBe('/admin/gs-commands');
});

it('offers commands that match, and hides the ones the user may not run', function () {
    // `Recount stock` and `Recount secrets` both match; only one is runnable, and
    // listing the label of the other would leak it whether or not the click were
    // refused afterwards.
    gsRegister([GsCommandResource::class]);

    $rows = app(PaletteCommands::class)->search('Recount');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->title)->toBe('Recount stock')
        ->and($rows[0]->kind)->toBe(PaletteRowKind::Command)
        ->and($rows[0]->actionName)->toBe('recount');
});

it('still offers a command that has to ask, because someone else can ask', function () {
    // Filtering these out at listing time would make the offer depend on which
    // page ⌘K was opened over.
    gsRegister([GsCommandResource::class]);

    expect(array_map(fn ($row) => $row->actionName, app(PaletteCommands::class)->search('Import')))
        ->toBe(['import']);
});

it('runs a command that has nothing to ask, and closes', function () {
    gsRegister([GsCommandResource::class]);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'Recount')
        ->call('select')
        ->assertSet('open', false)
        ->assertNoRedirect();

    expect(GsCommandResource::$ran)->toBe('recount');
});

it('hands a standalone command that has to ask to a host on screen', function () {
    // Not navigated, even though the owner has an index page: an index owns no
    // action host, so sending the user there for a modal that will not open is
    // worse than not moving them. A host already on screen is the only thing that
    // could answer, so it is asked.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'Import')
        ->call('select')
        ->assertNoRedirect()
        ->assertDispatched('wire-palette-action', name: 'import', arguments: []);

    expect(GsCommandResource::$ran)->toBeNull();
});

it('hands it on the same way when nothing routes a page at all', function () {
    gsRegister([GsCommandResource::class]);
    app()->instance(ResolvesPageUrls::class, new UnroutedPageUrls);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'Import')
        ->call('select')
        ->assertNoRedirect()
        ->assertDispatched('wire-palette-action', name: 'import', arguments: []);
});

it('checks again that the action may run, rather than trusting the row', function () {
    // A round trip happened between building the list and pressing Enter. An
    // action that became forbidden in between must not run because a stale row
    // said it could.
    gsRegister([GsCommandResource::class]);

    $component = Livewire::test(GlobalSearchPalette::class)->set('term', 'Recount');

    Gate::define('nope', fn (): bool => false);
    GsCommandResource::$ran = null;

    // Same name, now gated: the palette resolves the action from its owner again,
    // so a definition that changed under it is the one that decides.
    app()->bind(ClassifiesComponentActions::class, fn () => new class implements ClassifiesComponentActions
    {
        public function needsPrompt($action): bool
        {
            return false;
        }

        public function isRunnable($action, mixed $context = null): bool
        {
            return false;
        }
    });

    $component->call('select');

    expect(GsCommandResource::$ran)->toBeNull();
});

it('drills into a record to show what can be done with it', function () {
    gsRegister([GsCommandResource::class]);
    gsRouted();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1001')
        ->call('drillDown')
        ->assertSet('active', 0)
        ->assertSee('Cancel order')
        ->assertSee('Touch order')
        ->assertDontSee('Forbidden order thing')
        ->assertSee(__('wire-core::global-search.record_actions'));
});

it('gives a record action the record it was drilled into', function () {
    // The whole point of the second level: `commands($record)` and the runnable
    // check both have to be about the row the user picked, not about nothing.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    $order = GsOrder::where('reference', 'INV-1002')->firstOrFail();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1002')
        ->call('drillDown')
        ->call('moveDown')      // past `cancel`, onto `touch`
        ->call('select');

    expect(GsCommandResource::$ran)->toBe($order->getKey());
});

it('sends a record action that asks to the record page, which reads it', function () {
    // The one navigating branch, and it navigates because the far end exists:
    // `ResolvesOneRecord` reads `?action=` on arrival.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    $order = GsOrder::where('reference', 'INV-1001')->firstOrFail();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1001')
        ->call('drillDown')
        ->call('select')
        ->assertRedirect('/gs-commands/'.$order->getKey().'?action=cancel');
});

it('does not drill into a row whose owner offers nothing', function () {
    // Otherwise the key opens an empty list and the user has lost their results.
    gsRegister([GsOrderResource::class]);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1001')
        ->call('drillDown')
        ->assertSet('drilldown', null);
});

it('backs out of a drill-down without losing the search', function () {
    gsRegister([GsCommandResource::class]);
    gsRouted();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1001')
        ->call('drillDown')
        ->call('drillUp')
        ->assertSet('drilldown', null)
        ->assertSet('term', 'INV-1001')
        ->assertSee('INV-1001');
});

it('leaves a drill-down as soon as the term changes', function () {
    // The actions on screen belong to a record the new term may not even match.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-1001')
        ->call('drillDown')
        ->assertSet('drilldown', ['gs-commands', 1])
        ->set('term', 'REF')
        ->assertSet('drilldown', null);
});

it('draws records first, then navigation, then commands', function () {
    // Not taste, and not arbitrary: a term that matches both a record and a
    // command belongs to the record, because that is what the user was looking
    // for. Commands on top made Enter on "INV" run "Recount invoices" instead of
    // opening the invoice — caught by the browser driver, by nothing else.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    $component = Livewire::test(GlobalSearchPalette::class)->set('term', 'Orders');

    $kinds = array_map(
        fn (GlobalSearchResult $row): string => $row->kind->value,
        $component->instance()->flatResults(),
    );

    expect($kinds)->toBe(['navigation', 'command']);
});

it('carries the kind and the action through withUrl', function () {
    // It used to copy its fields positionally, which is one argument away from
    // silently turning a command back into a record.
    $row = new GlobalSearchResult(
        resourceKey: 'k',
        recordKey: null,
        title: 't',
        kind: PaletteRowKind::Command,
        actionName: 'recount',
    );

    expect($row->withUrl('/x'))
        ->kind->toBe(PaletteRowKind::Command)
        ->actionName->toBe('recount')
        ->url->toBe('/x');
});

it('adds no query per keystroke for navigation or commands', function () {
    // Both read declarations, not tables. If either ever reaches for the database
    // it does so once per keystroke per resource, which is what this pins.
    gsRegister([GsCommandResource::class]);
    gsRouted();

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(GlobalSearchPalette::class)->set('term', 'Orders');

    $queries = count(DB::getQueryLog());

    DB::disableQueryLog();

    // 'Orders' matches the menu entry and a command label, and no record.
    expect($queries)->toBe(1);
});

it('acts on the row that was activated, not on wherever the cursor sat', function () {
    // Measured in a browser before it was a test: Tab puts real focus on a row
    // without moving the keyboard cursor, so activating it opened a different
    // record — three rows away. A tap did the same, because a touch device fires
    // no `mouseenter` for the hover binding that used to keep the two in step.
    gsRegister([GsOrderResource::class]);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->assertSet('active', 0)
        // The redirect is the proof: row 0 is INV-1001 and row 1 is INV-1002, and
        // the cursor never moved. `active` is not asserted after — `close()` has
        // reset it by then, which is what closing is supposed to do.
        ->call('select', 1)
        ->assertRedirect('/orders/2');
});

it('still follows the cursor when the input is what was pressed', function () {
    // Enter in the search box names no row, because the cursor is the row.
    gsRegister([GsOrderResource::class]);

    Livewire::test(GlobalSearchPalette::class)
        ->set('term', 'INV-100')
        ->call('moveDown')
        ->call('select')
        ->assertRedirect('/orders/2');
});

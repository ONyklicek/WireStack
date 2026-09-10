<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Routing\ResourceRoutes;

/*
 * Creating and editing one of a resource's records.
 *
 * One schema serves both, which is the point: a create form and an edit form
 * that drift apart is the failure this shape prevents. So the assertions worth
 * making are that both really do render the same fields, that edit arrives
 * seeded and create does not, and that persistence stays the form's.
 */
class FpOrder extends Model
{
    protected $table = 'fp_orders';

    protected $guarded = [];

    public $timestamps = false;
}

class FpOrderResource implements DescribesResource, ProvidesResourceForm
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return FpOrder::class;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('number')->required(),
            TextInput::make('customer'),
        ]);
    }
}

/** Identity only — a resource is allowed to have no form. */
class FpListOnlyResource implements DescribesResource
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return FpOrder::class;
    }
}

class FpCreateOrder extends CreatePage
{
    protected static ?string $resource = FpOrderResource::class;
}

class FpEditOrder extends EditPage
{
    protected static ?string $resource = FpOrderResource::class;
}

class FpCreateFormless extends CreatePage
{
    protected static ?string $resource = FpListOnlyResource::class;
}

class FpCreateUndeclared extends CreatePage {}

class FpTitledCreate extends CreatePage
{
    protected static ?string $resource = FpOrderResource::class;

    protected ?string $title = 'Raise an order';
}

/** No model to look a key up against. */
class FpModellessResource implements DescribesResource, ProvidesResourceForm
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return null;
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('number')]);
    }
}

class FpTitledEdit extends EditPage
{
    protected static ?string $resource = FpOrderResource::class;

    protected ?string $title = 'Amend the order';
}

class FpEditModelless extends EditPage
{
    protected static ?string $resource = FpModellessResource::class;
}

beforeEach(function () {
    Schema::create('fp_orders', function (Blueprint $t) {
        $t->id();
        $t->string('number');
        $t->string('customer')->nullable();
    });

    FpOrder::insert([['id' => 1, 'number' => 'A-1', 'customer' => 'Acme']]);
});

afterEach(function () {
    Schema::dropIfExists('fp_orders');
});

// ─── One schema, both pages ──────────────────────────────────────────────────

it('renders the resource form on the create page', function () {
    Livewire::test(FpCreateOrder::class)
        ->assertSee('number', escape: false)
        ->assertSee('customer', escape: false);
});

it('renders the same fields on the edit page', function () {
    // Same schema object, so a field added to the resource reaches both pages —
    // which is the whole reason one form() serves create and edit.
    Livewire::test(FpEditOrder::class, ['record' => 1])
        ->assertSee('number', escape: false)
        ->assertSee('customer', escape: false);
});

it('arrives seeded on edit and blank on create', function () {
    expect(Livewire::test(FpEditOrder::class, ['record' => 1])->instance()->form->getState())
        ->toMatchArray(['number' => 'A-1', 'customer' => 'Acme']);

    expect(Livewire::test(FpCreateOrder::class)->instance()->form->getState()['number'] ?? null)
        ->not->toBe('A-1');
});

// ─── Persistence is the form's ───────────────────────────────────────────────

it('creates a record through the form', function () {
    Livewire::test(FpCreateOrder::class)
        ->set('data.number', 'B-2')
        ->set('data.customer', 'Globex')
        ->call('save');

    expect(FpOrder::where('number', 'B-2')->first()?->customer)->toBe('Globex');
});

it('updates the mounted record through the form', function () {
    // The model is bound from the resource's modelClass() plus the key, so the
    // page never asks the resource to repeat which entity it owns.
    Livewire::test(FpEditOrder::class, ['record' => 1])
        ->set('data.customer', 'Initech')
        ->call('save');

    expect(FpOrder::find(1)->customer)->toBe('Initech');
});

it('refuses to save what the schema rejects', function () {
    Livewire::test(FpCreateOrder::class)
        ->set('data.number', '')
        ->call('save')
        ->assertHasErrors('data.number');

    expect(FpOrder::count())->toBe(1);
});

// ─── Titles ──────────────────────────────────────────────────────────────────

it('prefers an explicit title over the resource label', function () {
    // Both form pages, because each carries its own fallback and a shared
    // property is exactly the kind of thing that gets wired on one and not the
    // other.
    expect((new FpTitledCreate)->getTitle())->toBe('Raise an order')
        ->and((new FpTitledEdit)->getTitle())->toBe('Amend the order');
});

it('refuses to resolve a record for a resource with no model', function () {
    // A DataSource-backed resource has no model to look a key up against, so the
    // page says so instead of resolving null and turning an edit into an insert.
    expect(fpRefusal(FpEditModelless::class, ['record' => 1]))
        ->toContain('could not resolve its record')
        ->toContain('modelClass()');
});

it('titles itself from the resource, in the singular', function () {
    // A list is titled by the plural; a form page is about one record.
    expect((new FpCreateOrder)->getTitle())->toBe('New Fp Order')
        ->and((new FpEditOrder)->getTitle())->toBe('Edit Fp Order');
});

// ─── Half-declared pages ─────────────────────────────────────────────────────

function fpRefusal(string $page, array $params = []): string
{
    try {
        Livewire::test($page, $params);
    } catch (Throwable $e) {
        return $e->getMessage();
    }

    return '';
}

it('refuses a resource that has no form to give', function () {
    expect(fpRefusal(FpCreateFormless::class))
        ->toContain(FpListOnlyResource::class)
        ->toContain(ProvidesResourceForm::class);
});

it('refuses a page that declares nothing at all', function () {
    expect(fpRefusal(FpCreateUndeclared::class))->toContain('has nothing to render');
});

it('refuses an edit page mounted without a record', function () {
    // Without this it would resolve null, seed an empty form and silently save a
    // new row — an update turning into an insert is the worst of the failures.
    expect(fpRefusal(FpEditOrder::class))->toContain('mounted without a record');
});

// ─── Where a save lands ──────────────────────────────────────────────────────

/*
 * A created record exists and the form that made it is still full, so the page
 * that made it is the one place the user must not be left standing: the next
 * press of the same button files a second one. An edit has the opposite shape —
 * it is already on the record's own page — which is why only one of the two
 * pages redirects, and why both say so through the same overridable method.
 */

/** Routed with the record's own page in reach: view first, then edit. */
class FpRoutedResource extends FpOrderResource implements ProvidesPages
{
    public static function key(): string
    {
        return 'fp-routed';
    }

    public static function pages(): array
    {
        return [
            'index' => FpRoutedList::class,
            'create' => FpCreateRouted::class,
            'view' => FpRoutedStandIn::class,
            'edit' => FpRoutedStandIn::class,
        ];
    }
}

/** No view page, so the fallback below it is the one that has to answer. */
class FpNoViewResource extends FpOrderResource implements ProvidesPages
{
    public static function key(): string
    {
        return 'fp-no-view';
    }

    public static function pages(): array
    {
        return [
            'index' => FpRoutedList::class,
            'create' => FpCreateNoView::class,
            'edit' => FpRoutedStandIn::class,
        ];
    }
}

/** A record page this user may not open, declared the way a resource declares it. */
class FpGatedResource extends FpOrderResource implements ProvidesPages
{
    public static function key(): string
    {
        return 'fp-gated';
    }

    public static function pages(): array
    {
        return [
            'index' => FpRoutedList::class,
            'create' => FpCreateGated::class,
            'view' => RoutePage::make(FpRoutedStandIn::class)->permission('fp.view'),
        ];
    }
}

/** Nothing but a list — nowhere to send a record. */
class FpIndexOnlyResource extends FpOrderResource implements ProvidesPages
{
    public static function key(): string
    {
        return 'fp-index-only';
    }

    public static function pages(): array
    {
        return [
            'index' => FpRoutedList::class,
            'create' => FpCreateIndexOnly::class,
        ];
    }
}

/*
 * Route targets. Livewire components rather than plain classes, because Laravel
 * refuses a route action that is neither invokable nor a component.
 */
class FpRoutedStandIn extends Component
{
    public function render(): string
    {
        return '<div>stand-in</div>';
    }
}

class FpRoutedList extends FpRoutedStandIn {}

class FpCreateRouted extends CreatePage
{
    protected static ?string $resource = FpRoutedResource::class;
}

class FpCreateNoView extends CreatePage
{
    protected static ?string $resource = FpNoViewResource::class;
}

class FpCreateGated extends CreatePage
{
    protected static ?string $resource = FpGatedResource::class;
}

class FpCreateIndexOnly extends CreatePage
{
    protected static ?string $resource = FpIndexOnlyResource::class;
}

class FpEditRouted extends EditPage
{
    protected static ?string $resource = FpRoutedResource::class;
}

/**
 * Register the resource, route it, and make the names resolvable.
 *
 * The refresh is not optional: a route registered during a test is in the
 * collection but not yet in the name map Laravel builds at boot, so without it
 * every `urlFor()` answers null and a redirect assertion fails as "the page did
 * not redirect" rather than as "the test asked too early".
 */
function fpRoute(string $resource): void
{
    app(ResourceRegistry::class)->register($resource);

    Route::wireResources();
    Route::getRoutes()->refreshNameLookups();
}

it('sends a created record to its own page', function () {
    fpRoute(FpRoutedResource::class);

    Livewire::test(FpCreateRouted::class)
        ->set('data.number', 'C-3')
        ->call('save')
        ->assertRedirect(ResourceRoutes::urlFor('fp-routed', 'view', [
            'record' => FpOrder::where('number', 'C-3')->value('id'),
        ]));
});

it('falls back to the edit page when the resource has no view page', function () {
    fpRoute(FpNoViewResource::class);

    Livewire::test(FpCreateNoView::class)
        ->set('data.number', 'D-4')
        ->call('save')
        ->assertRedirect(ResourceRoutes::urlFor('fp-no-view', 'edit', [
            'record' => FpOrder::where('number', 'D-4')->value('id'),
        ]));
});

it('falls back to the list when no page takes a record', function () {
    fpRoute(FpIndexOnlyResource::class);

    Livewire::test(FpCreateIndexOnly::class)
        ->set('data.number', 'E-5')
        ->call('save')
        ->assertRedirect(ResourceRoutes::urlFor('fp-index-only'));
});

it('skips a record page this user may not open', function () {
    // The same permission the resource declared is `can:` middleware on that
    // route, so redirecting there would be a 403 — strictly worse than the list,
    // which is where a create with nowhere better to go already lands.
    fpRoute(FpGatedResource::class);

    Gate::define('fp.view', fn (): bool => false);
    $this->actingAs(new class extends Authenticatable {});

    // Routed, and still not where this save lands — otherwise the assertion
    // below would pass just as well on a page that never asked.
    expect(ResourceRoutes::urlFor('fp-gated', 'view', ['record' => 1]))->not->toBeNull();

    Livewire::test(FpCreateGated::class)
        ->set('data.number', 'F-6')
        ->call('save')
        ->assertRedirect(ResourceRoutes::urlFor('fp-gated'));
});

it('sends the user who does hold it to the page it guards', function () {
    // The other half of the same declaration: a permission is a reason to skip a
    // page, not a reason to stop offering it.
    fpRoute(FpGatedResource::class);

    Gate::define('fp.view', fn (): bool => true);
    $this->actingAs(new class extends Authenticatable {});

    Livewire::test(FpCreateGated::class)
        ->set('data.number', 'F-7')
        ->call('save')
        ->assertRedirect(ResourceRoutes::urlFor('fp-gated', 'view', [
            'record' => FpOrder::where('number', 'F-7')->value('id'),
        ]));
});

it('stays put after an edit', function () {
    // Not an oversight: the record exists, the page is already its own, and the
    // form now holds exactly what was written.
    fpRoute(FpRoutedResource::class);

    Livewire::test(FpEditRouted::class, ['record' => 1])
        ->set('data.customer', 'Umbrella')
        ->call('save')
        ->assertNoRedirect();

    expect(FpOrder::find(1)->customer)->toBe('Umbrella');
});

it('stays put when nothing routes the resource at all', function () {
    // A page mounted by hand, or rendered inside something else, has no URL to
    // go to — and the page it is on is still the right one.
    Livewire::test(FpCreateOrder::class)
        ->set('data.number', 'G-7')
        ->call('save')
        ->assertNoRedirect();

    expect(FpOrder::where('number', 'G-7')->exists())->toBeTrue();
});

// ─── The toast the redirect carries ──────────────────────────────────────────

/*
 * A success notification is a browser event, and a redirect replaces the
 * document that would have shown it. The flash is what crosses that boundary —
 * and Livewire is what makes it safe to leave one on every save: an update that
 * did not redirect has its flashed keys forgotten, so only the toast that
 * genuinely could not be delivered survives to the next page.
 */

it('leaves the toast for the page it redirects to', function () {
    fpRoute(FpRoutedResource::class);

    Livewire::test(FpCreateRouted::class)
        ->set('data.number', 'H-8')
        ->call('save');

    expect(session('table-notification'))->toMatchArray(['type' => 'success']);
});

it('takes the toast back when the save stayed put', function () {
    // Livewire's own doing, pinned here because the whole design leans on it:
    // `SupportRedirects` forgets everything flashed during an update that did
    // not redirect, so the edit page's already-shown toast cannot reappear on
    // whatever page the user opens next. Without that, leaving a flash on every
    // save would put a stale "Saved" on the next full page load.
    Livewire::test(FpEditRouted::class, ['record' => 1])
        ->set('data.customer', 'Soylent')
        ->call('save');

    expect(session('table-notification'))->toBeNull();
});

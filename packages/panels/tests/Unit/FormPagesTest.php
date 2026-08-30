<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;

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

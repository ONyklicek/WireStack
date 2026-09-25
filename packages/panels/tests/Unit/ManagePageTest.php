<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\ManagePage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * A whole resource on one page — the list, with create and edit as modals.
 *
 * Worth proving: that both modals render the resource's one form, that what
 * they save lands in the model, and that the policy decides who may do which.
 */
class MpTag extends Model
{
    protected $table = 'mp_tags';

    protected $guarded = [];

    public $timestamps = false;
}

class MpTagResource implements DescribesResource, ProvidesResourceForm, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return MpTag::class;
    }

    public function table(Table $table): Table
    {
        return $table->paginated(false)->columns([TextColumn::make('name')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->required()]);
    }
}

/** A list with no form: nothing to create or edit in a modal. */
class MpListOnlyResource implements DescribesResource, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return MpTag::class;
    }

    public static function key(): string
    {
        return 'mp-list-only';
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}

class MpManageTags extends ManagePage
{
    protected static ?string $resource = MpTagResource::class;
}

class MpManageListOnly extends ManagePage
{
    protected static ?string $resource = MpListOnlyResource::class;
}

class MpTagPolicy
{
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, MpTag $tag): bool
    {
        return $tag->getAttribute('name') !== 'locked';
    }

    public function delete(Authenticatable $user, MpTag $tag): bool
    {
        return false;
    }
}

beforeEach(function () {
    Schema::create('mp_tags', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    MpTag::query()->create(['name' => 'urgent']);

    $user = new Authenticatable;
    $user->setAttribute('id', 1);
    $this->be($user);
});

it('offers New as a modal and creates through the model', function () {
    $component = Livewire::test(MpManageTags::class);

    expect($component->html())->toContain(e("openHeaderActionModal('create')"));

    $component->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.actions.0.data.name', 'billing')
        ->call('submitActionModal');

    expect(MpTag::query()->where('name', 'billing')->exists())->toBeTrue();
});

it('validates the modal with the resource form rules', function () {
    Livewire::test(MpManageTags::class)
        ->call('openHeaderActionModal', 'create')
        ->call('submitActionModal')
        ->assertHasErrors();

    expect(MpTag::query()->count())->toBe(1);
});

it('edits a row in a modal seeded from the record', function () {
    $component = Livewire::test(MpManageTags::class)->call('openActionModal', '1', 'edit');

    expect($component->get('tableState.modal.actions.0.data.name'))->toBe('urgent');

    $component->set('tableState.modal.actions.0.data.name', 'critical')->call('submitActionModal');

    expect(MpTag::query()->find(1)->getAttribute('name'))->toBe('critical');
});

it('deletes a row', function () {
    Livewire::test(MpManageTags::class)->call('executeTableAction', '1', 'delete', true);

    expect(MpTag::query()->find(1))->toBeNull();
});

it('lets the model policy decide each action', function () {
    Gate::policy(MpTag::class, MpTagPolicy::class);
    MpTag::query()->create(['name' => 'locked']);

    $html = Livewire::test(MpManageTags::class)->html();

    expect($html)->not->toContain('data-testid="action-create"')
        ->and(substr_count($html, 'data-testid="action-edit"'))->toBe(1)
        ->and($html)->not->toContain('data-testid="action-delete"');
});

it('offers neither modal where the resource has no form', function () {
    $html = Livewire::test(MpManageListOnly::class)->html();

    expect($html)->not->toContain('data-testid="action-create"')
        ->and($html)->not->toContain('data-testid="action-edit"');
});

it('lets whoever opens an unguarded page manage it when the model has no policy', function () {
    // No signed-in user and no policy: the page's route is the guard, and an
    // authorization callback that refused a guest would leave a list with no
    // way to change it.
    auth()->logout();

    $html = Livewire::test(MpManageTags::class)->html();

    expect($html)->toContain('data-testid="action-create"')
        ->and($html)->toContain('data-testid="action-edit"');
});

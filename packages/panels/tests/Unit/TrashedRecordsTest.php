<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ManagesTrashedRecords;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * A resource that manages its trash, on the list and on the record's pages.
 *
 * The thing worth proving is that the two halves agree: a row the list offers
 * *Restore* on has a page that opens, and every trash action is asked of the
 * same rule as *Delete* — the policy when there is one, the edit page's
 * permission when there is not.
 */
class TrDoc extends Model
{
    use SoftDeletes;

    protected $table = 'tr_docs';

    protected $guarded = [];

    public $timestamps = false;
}

class TrPlainDoc extends Model
{
    protected $table = 'tr_docs';

    protected $guarded = [];

    public $timestamps = false;
}

class TrDocResource implements DescribesResource, ManagesTrashedRecords, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return TrDoc::class;
    }

    public static function pages(): array
    {
        return [
            'index' => TrListDocs::class,
            'view' => TrViewDoc::class,
            'edit' => RoutePage::make(TrEditDoc::class)->permission('docs.update'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->paginated(false)->columns([TextColumn::make('title')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('title')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('title')]);
    }
}

/** Declares the contract over a model with no trash to manage. */
class TrBrokenResource implements DescribesResource, ManagesTrashedRecords, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return TrPlainDoc::class;
    }

    public static function key(): string
    {
        return 'tr-broken';
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')]);
    }
}

/** The same entity, trash not managed: trashed records stay out of sight. */
class TrUnmanagedResource implements DescribesResource, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return TrDoc::class;
    }

    public static function key(): string
    {
        return 'tr-unmanaged';
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('title')]);
    }
}

class TrListDocs extends ListPage
{
    protected static ?string $resource = TrDocResource::class;
}

class TrViewDoc extends ViewPage
{
    protected static ?string $resource = TrDocResource::class;

    protected function headerActions(): array
    {
        return [$this->deleteHeaderAction(), $this->restoreHeaderAction(), $this->forceDeleteHeaderAction()];
    }
}

class TrEditDoc extends EditPage
{
    protected static ?string $resource = TrDocResource::class;
}

class TrBrokenList extends ListPage
{
    protected static ?string $resource = TrBrokenResource::class;
}

class TrUnmanagedView extends ViewPage
{
    protected static ?string $resource = TrUnmanagedResource::class;
}

class TrDocPolicy
{
    public function restore(Authenticatable $user, TrDoc $doc): bool
    {
        return $doc->getAttribute('title') !== 'Sealed';
    }

    public function forceDelete(Authenticatable $user, TrDoc $doc): bool
    {
        return false;
    }
}

function trSignIn(array $abilities = ['docs.update']): void
{
    Gate::before(fn ($user, string $ability) => in_array($ability, $abilities, true) ? true : null);

    $user = new Authenticatable;
    $user->setAttribute('id', 1);

    test()->be($user);
}

beforeEach(function () {
    Schema::create('tr_docs', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->softDeletes();
    });

    app(ResourceRegistry::class)->register(TrDocResource::class);
    Route::middleware('web')->group(fn () => Route::wireResources(only: ['tr-docs']));

    TrDoc::query()->create(['title' => 'Live']);
    TrDoc::query()->create(['title' => 'Binned'])->delete();
});

it('gives the list a trashed filter', function () {
    trSignIn();

    $table = Livewire::test(TrListDocs::class)->instance()->getTable();

    expect(collect($table->getFilters())->map->getName()->all())->toContain('trashed')
        ->and($table->resolvesTrashedRecords())->toBeTrue();
});

it('lists trashed rows only when the filter asks, with Restore on them alone', function () {
    trSignIn();

    $default = Livewire::test(TrListDocs::class)->html();
    $withTrashed = Livewire::test(TrListDocs::class)->set('tableState.filters.trashed.value', 'with')->html();

    expect($default)->toContain('Live')->not->toContain('Binned')
        ->and($withTrashed)->toContain('Binned')
        ->and(substr_count($withTrashed, 'data-testid="action-restore"'))->toBe(1);
});

it('restores a trashed row from the list', function () {
    trSignIn();

    Livewire::test(TrListDocs::class)
        ->call('openActionModal', '2', 'restore')
        ->call('submitActionModal');

    expect(TrDoc::query()->find(2))->not->toBeNull();
});

it('restores and force-deletes only the trashed records of a selection', function () {
    trSignIn();

    Livewire::test(TrListDocs::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '2')
        ->call('executeBulkActionWithData', 'forceDelete', []);

    // The live one survives a sweep that included it; the trashed one is gone for good.
    expect(TrDoc::query()->find(1))->not->toBeNull()
        ->and(TrDoc::withTrashed()->find(2))->toBeNull();
});

it('restores the trashed records of a selection', function () {
    trSignIn();

    Livewire::test(TrListDocs::class)
        ->call('toggleRecordSelection', '2')
        ->call('executeBulkActionWithData', 'restore', []);

    expect(TrDoc::query()->find(2))->not->toBeNull();
});

it('asks the policy about each trashed record', function () {
    trSignIn();
    Gate::policy(TrDoc::class, TrDocPolicy::class);

    TrDoc::query()->create(['title' => 'Sealed'])->delete();

    Livewire::test(TrListDocs::class)
        ->call('toggleRecordSelection', '2')
        ->call('toggleRecordSelection', '3')
        ->call('executeBulkActionWithData', 'restore', []);

    expect(TrDoc::query()->find(2))->not->toBeNull()
        ->and(TrDoc::query()->find(3))->toBeNull();
});

it('opens the page of a trashed record', function () {
    trSignIn();

    $html = Livewire::test(TrViewDoc::class, ['record' => 2])->html();

    expect($html)->toContain('Binned')
        ->and($html)->toContain('data-testid="action-restore"')
        ->and($html)->toContain('data-testid="action-forceDelete"')
        ->and($html)->not->toContain('data-testid="action-delete"');
});

it('offers Delete, not Restore, on a live record', function () {
    trSignIn();

    $html = Livewire::test(TrViewDoc::class, ['record' => 1])->html();

    expect($html)->toContain('data-testid="action-delete"')
        ->and($html)->not->toContain('data-testid="action-restore"')
        ->and($html)->not->toContain('data-testid="action-forceDelete"');
});

it('restores from the record page and stays on it', function () {
    trSignIn();

    Livewire::test(TrViewDoc::class, ['record' => 2])
        ->call('mountAction', 'restore')
        ->call('callMountedAction')
        ->assertNoRedirect();

    expect(TrDoc::query()->find(2))->not->toBeNull();
});

it('force-deletes from the record page and goes back to the list', function () {
    trSignIn();

    Livewire::test(TrViewDoc::class, ['record' => 2])
        ->call('mountAction', 'forceDelete')
        ->call('callMountedAction')
        ->assertRedirect(url('tr-docs'));

    expect(TrDoc::withTrashed()->find(2))->toBeNull();
});

it('withholds trash actions from someone who may not edit', function () {
    trSignIn(abilities: []);

    expect(Livewire::test(TrViewDoc::class, ['record' => 2])->html())
        ->not->toContain('data-testid="action-restore"');
});

it('keeps a trashed record out of sight where the resource does not manage its trash', function () {
    trSignIn();

    Livewire::test(TrUnmanagedView::class, ['record' => 2])->assertNotFound();
});

it('refuses the contract over a model that does not soft-delete', function () {
    expect($this->refusalMessage(TrBrokenList::class))->toContain('does not use SoftDeletes');
});

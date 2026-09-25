<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesBreadcrumbs;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WirePanels\Resources\Pages\CreatePage;
use NyonCode\WirePanels\Resources\Pages\EditPage;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * The actions a page draws beside its heading.
 *
 * Every page runs them through the engine it already has — the list through its
 * table's, the others through `WithActions` — so what is worth asserting is that
 * a click lands in that engine and finds the page's action, that a record page
 * hands its record to the action it runs, and that the defaults are the safe
 * ones: *New* when the create page is there to open, *Delete* only when asked.
 */

class HaTicket extends Model
{
    protected $table = 'ha_tickets';

    protected $guarded = [];

    public $timestamps = false;
}

class HaTicketResource implements DescribesResource, ProvidesPages, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return HaTicket::class;
    }

    public static function pages(): array
    {
        return [
            'index' => HaListTickets::class,
            'create' => RoutePage::make(HaCreateTicket::class)->permission('tickets.create'),
            'view' => HaViewTicket::class,
            'edit' => RoutePage::make(HaEditTicket::class)->permission('tickets.update'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('subject')]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('subject')]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('subject')]);
    }
}

/** A resource with a view page and nothing to edit it with — read-only. */
class HaReadOnlyResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return HaTicket::class;
    }

    public static function key(): string
    {
        return 'ha-archive';
    }

    public static function pages(): array
    {
        return ['view' => HaViewArchived::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('subject')]);
    }
}

class HaListTickets extends ListPage
{
    protected static ?string $resource = HaTicketResource::class;

    public static int $pinged = 0;

    protected function headerActions(): array
    {
        return [
            ...parent::headerActions(),
            Action::make('ping')->label('Ping')->action(fn () => static::$pinged++),
            ActionGroup::make([
                Action::make('grouped')->label('Grouped')->action(fn () => static::$pinged += 10),
            ]),
        ];
    }
}

class HaCreateTicket extends CreatePage
{
    protected static ?string $resource = HaTicketResource::class;

    public static int $imported = 0;

    protected function headerActions(): array
    {
        return [Action::make('import')->label('Import')->action(fn () => static::$imported++)];
    }
}

class HaEditTicket extends EditPage
{
    protected static ?string $resource = HaTicketResource::class;

    protected function headerActions(): array
    {
        return [
            $this->deleteHeaderAction(),
            Action::make('close')
                ->label('Close')
                ->visible(fn (?Model $record): bool => $record?->getAttribute('subject') !== 'Hidden')
                ->action(fn (Model $record) => $record->update(['subject' => 'Closed: '.$record->getAttribute('subject')])),
        ];
    }
}

class HaViewTicket extends ViewPage
{
    protected static ?string $resource = HaTicketResource::class;

    protected function headerActions(): array
    {
        return [$this->deleteHeaderAction()];
    }
}

/** The defaults, untouched: a view page asks for no *Delete* of its own accord. */
class HaPlainViewTicket extends ViewPage
{
    protected static ?string $resource = HaTicketResource::class;
}

/** An infolist whose section carries a callback action of its own. */
class HaFlaggingResource extends HaTicketResource
{
    public static function key(): string
    {
        return 'ha-flagging';
    }

    public static function pages(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Ticket')
                ->headerActions([
                    Action::make('flag')->label('Flag')->action(fn (Model $record) => $record->update(['subject' => 'Flagged'])),
                ])
                ->schema([TextEntry::make('subject')]),
        ]);
    }
}

class HaViewFlagging extends ViewPage
{
    protected static ?string $resource = HaFlaggingResource::class;
}

class HaViewArchived extends ViewPage
{
    protected static ?string $resource = HaReadOnlyResource::class;

    protected function headerActions(): array
    {
        return [$this->deleteHeaderAction()];
    }
}

class HaBoard extends Page implements ProvidesBreadcrumbs
{
    protected static string $view = 'ha::board';

    protected ?string $title = 'Board';

    public int $moved = 0;

    protected function headerActions(): array
    {
        return [Action::make('shuffle')->label('Shuffle')->action(fn () => $this->moved++)];
    }

    /** A card's own button: declared in `actions()`, run by the same engine. */
    protected function actions(): array
    {
        return [Action::make('moveCard')->action(fn () => $this->moved += 100)];
    }

    public function breadcrumbs(): array
    {
        return [NavigationItem::make('Projects')->url('/projects'), NavigationItem::make('Board')];
    }

    protected function getViewData(): array
    {
        return ['lanes' => ['Todo', 'Done']];
    }
}

class HaViewless extends Page {}

/** Nothing but a view: no title, no trail, no actions, no extra view data. */
class HaBarePage extends Page
{
    protected static string $view = 'ha::bare';
}

class HaTicketPolicy
{
    public function delete(Authenticatable $user, HaTicket $ticket): bool
    {
        return $ticket->getAttribute('subject') !== 'Protected';
    }
}

function haSignIn(array $abilities = ['tickets.create', 'tickets.update']): void
{
    Gate::before(fn ($user, string $ability) => in_array($ability, $abilities, true) ? true : null);

    $user = new Authenticatable;
    $user->setAttribute('id', 1);

    test()->be($user);
}

function haRoutes(): void
{
    Route::middleware('web')->group(fn () => Route::wireResources());
}

beforeEach(function () {
    Schema::create('ha_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('subject');
    });

    app(ResourceRegistry::class)->register(HaTicketResource::class);
    app(ResourceRegistry::class)->register(HaReadOnlyResource::class);

    view()->addNamespace('ha', __DIR__.'/../fixtures/views/header-actions');

    HaListTickets::$pinged = 0;
    HaCreateTicket::$imported = 0;
});

it('offers New on a list whose create page this user may open', function () {
    haRoutes();
    haSignIn();

    $html = Livewire::test(HaListTickets::class)->html();

    expect($html)->toContain('data-testid="page-header-actions"')
        ->and($html)->toContain('data-testid="action-create"')
        ->and($html)->toContain('ha-tickets/create')
        ->and($html)->toContain('wire:navigate');
});

it('offers no New where the create page is not routed', function () {
    haSignIn();

    expect(Livewire::test(HaListTickets::class)->html())->not->toContain('data-testid="action-create"');
});

it('offers no New to someone the create route would refuse', function () {
    haRoutes();
    haSignIn(abilities: []);

    expect(Livewire::test(HaListTickets::class)->html())->not->toContain('data-testid="action-create"');
});

it('runs a list page action through the table engine', function () {
    haRoutes();
    haSignIn();

    $component = Livewire::test(HaListTickets::class);

    expect($component->html())->toContain(e("executeHeaderAction('ping')"));

    $component->call('executeHeaderAction', 'ping');

    expect(HaListTickets::$pinged)->toBe(1);
});

it('finds a list page action folded into a group', function () {
    haSignIn();

    Livewire::test(HaListTickets::class)->call('executeHeaderAction', 'grouped');

    expect(HaListTickets::$pinged)->toBe(10);
});

it('still finds the table header actions beside the page ones', function () {
    haSignIn();

    $component = Livewire::test(HaListTickets::class);

    expect(fn () => $component->call('executeHeaderAction', 'nothing-by-this-name'))->not->toThrow(Throwable::class)
        ->and(HaListTickets::$pinged)->toBe(0);
});

it('hosts actions on a create page, which had no action runtime before', function () {
    haSignIn();

    $component = Livewire::test(HaCreateTicket::class);

    expect($component->html())->toContain(e("mountAction('import')"));

    $component->call('mountAction', 'import');

    expect(HaCreateTicket::$imported)->toBe(1);
});

it('runs an edit page action against the page record', function () {
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    Livewire::test(HaEditTicket::class, ['record' => $ticket->getKey()])
        ->call('mountAction', 'close');

    expect($ticket->fresh()->getAttribute('subject'))->toBe('Closed: Printer');
});

it('asks a record-aware visibility about the page record', function () {
    haSignIn();

    $hidden = HaTicket::query()->create(['subject' => 'Hidden']);

    expect(Livewire::test(HaEditTicket::class, ['record' => $hidden->getKey()])->html())
        ->not->toContain(e("mountAction('close')"));
});

it('draws no Delete unless the page asks for it', function () {
    haRoutes();
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    expect(Livewire::test(HaPlainViewTicket::class, ['record' => $ticket->getKey()])->html())
        ->not->toContain('data-testid="action-delete"')
        ->not->toContain('data-testid="page-header-actions"');
});

it('deletes the record and goes back to the list', function () {
    haRoutes();
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    $component = Livewire::test(HaViewTicket::class, ['record' => $ticket->getKey()]);

    expect($component->html())->toContain('data-testid="action-delete"');

    $component->call('mountAction', 'delete')
        ->call('callMountedAction')
        ->assertRedirect(url('ha-tickets'));

    expect(HaTicket::query()->find($ticket->getKey()))->toBeNull();
});

it('stays on the page after a delete where no list is routed', function () {
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    // Unrouted, so no edit page to fall back on either: a policy has to say yes.
    Gate::policy(HaTicket::class, HaTicketPolicy::class);

    Livewire::test(HaEditTicket::class, ['record' => $ticket->getKey()])
        ->call('mountAction', 'delete')
        ->call('callMountedAction')
        ->assertNoRedirect();

    expect(HaTicket::query()->find($ticket->getKey()))->toBeNull();
});

it('lets whoever may edit delete, when the model has no policy', function () {
    haRoutes();
    haSignIn(abilities: ['tickets.create']);

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    $component = Livewire::test(HaViewTicket::class, ['record' => $ticket->getKey()]);

    expect($component->html())->not->toContain('data-testid="action-delete"');

    $component->call('mountAction', 'delete')->call('callMountedAction');

    expect(HaTicket::query()->find($ticket->getKey()))->not->toBeNull();
});

it('refuses Delete on a resource that has nothing to edit with', function () {
    // No policy and no edit page: a read-only resource must not grow a Delete
    // button because nobody wrote a policy for it.
    haRoutes();
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    expect(Livewire::test(HaViewArchived::class, ['record' => $ticket->getKey()])->html())
        ->not->toContain('data-testid="action-delete"');
});

it('lets the model policy decide when there is one', function () {
    haRoutes();
    haSignIn();
    Gate::policy(HaTicket::class, HaTicketPolicy::class);

    $open = HaTicket::query()->create(['subject' => 'Printer']);
    $protected = HaTicket::query()->create(['subject' => 'Protected']);

    expect(Livewire::test(HaViewTicket::class, ['record' => $open->getKey()])->html())->toContain('data-testid="action-delete"')
        ->and(Livewire::test(HaViewTicket::class, ['record' => $protected->getKey()])->html())->not->toContain('data-testid="action-delete"');
});

it('draws a page of the application own with the shared heading', function () {
    haSignIn();

    $html = Livewire::test(HaBoard::class)->html();

    expect($html)->toContain('Board')
        ->and($html)->toContain('Projects')
        ->and($html)->toContain('data-testid="ha-board"')
        ->and($html)->toContain('Todo')
        ->and($html)->toContain(e("mountAction('shuffle')"));
});

it('runs a page header action and its own actions through one engine', function () {
    haSignIn();

    $component = Livewire::test(HaBoard::class)
        ->call('mountAction', 'shuffle')
        ->call('mountAction', 'moveCard');

    expect($component->get('moved'))->toBe(101);
});

it('refuses a page of the application own that names no view', function () {
    expect($this->refusalMessage(HaViewless::class))->toContain('has no content view');
});

it('runs an infolist action on the view page, which had nowhere to run it before', function () {
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    Livewire::test(HaViewFlagging::class, ['record' => $ticket->getKey()])
        ->call('callInfolistAction', 'flag');

    expect($ticket->fresh()->getAttribute('subject'))->toBe('Flagged');
});

it('opens a header action the palette named in the query string', function () {
    // A stock record page composes the action host now, so the palette's
    // hand-off lands: the confirmation opens, and nothing is deleted yet.
    haRoutes();
    haSignIn();

    $ticket = HaTicket::query()->create(['subject' => 'Printer']);

    Livewire::withQueryParams(['action' => 'delete'])
        ->test(HaViewTicket::class, ['record' => $ticket->getKey()])
        ->assertSet('actionModalOpen', true);

    expect(HaTicket::query()->find($ticket->getKey()))->not->toBeNull();
});

it('draws a bare page of the application own with no heading at all', function () {
    $html = Livewire::test(HaBarePage::class)->html();

    expect($html)->toContain('data-testid="ha-bare"')
        ->and($html)->not->toContain('<h1')
        ->and($html)->not->toContain('data-testid="page-header-actions"');
});

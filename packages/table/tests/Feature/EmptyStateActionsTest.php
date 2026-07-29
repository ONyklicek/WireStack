<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

// ─── Test model & component ──────────────────────────────────────

class EsaPost extends Model
{
    protected $table = 'esa_posts';

    protected $guarded = [];

    public $timestamps = false;
}

class EsaComponent extends Component
{
    /** @var array<int, string> */
    public array $ran = [];

    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(EsaPost::class)
            ->paginated(false)
            ->columns([TextColumn::make('title')])
            ->filters([SelectFilter::make('status')->options(['draft' => 'Draft'])])
            ->emptyState('No posts yet', 'Write the first one.')
            ->emptyStateActions([
                Action::make('createLink')->label('Create post')->url('/posts/create'),
                Action::make('createInline')
                    ->label('Quick create')
                    ->action(fn () => $this->ran[] = 'createInline'),
                Action::make('createModal')
                    ->label('Create with form')
                    ->form(fn () => Form::make()->schema([TextInput::make('title')]))
                    ->action(fn () => $this->ran[] = 'createModal'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class EsaStackedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(EsaPost::class)
            ->paginated(false)
            ->columns([TextColumn::make('title')])
            ->filters([SelectFilter::make('status')->options(['draft' => 'Draft'])])
            ->stackedOnMobile()
            ->emptyState('No posts yet', 'Write the first one.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create post')
                    ->keyboardShortcut('mod+n')
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('esa_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('esa_posts');
});

// ─── Rendering ───────────────────────────────────────────────────

it('renders the empty state actions when the table has no records', function () {
    Livewire::test(EsaComponent::class)
        ->assertSee('No posts yet')
        ->assertSee('Create post')
        ->assertSee('Quick create')
        ->assertSeeHtml('href="/posts/create"');
});

it('does not render the empty state actions once a record exists', function () {
    EsaPost::create(['title' => 'Hello']);

    Livewire::test(EsaComponent::class)
        ->assertSee('Hello')
        ->assertDontSee('Create post');
});

// A table emptied by a filter still holds records behind it, so the offer is to
// clear the filter — not to create a record that already exists.
it('offers the filter reset instead of the empty state actions when a filter emptied the table', function () {
    EsaPost::create(['title' => 'Hello', 'status' => 'published']);

    Livewire::test(EsaComponent::class)
        ->set('tableState.filters.status.value', 'draft')
        ->assertSeeHtml('data-testid="table-filter-reset"')
        ->assertDontSee('Create post')
        ->assertDontSee('Quick create');
});

// The stacked-card layout renders its own empty state, and both layouts sit in
// the document at every width — without the card branch the actions would be
// missing on a phone.
it('renders the empty state actions in the stacked card layout too', function () {
    $html = Livewire::test(EsaStackedComponent::class)->html();

    expect(substr_count($html, 'data-testid="action-create"'))->toBe(2);
});

it('offers the filter reset in the stacked card empty state', function () {
    EsaPost::create(['title' => 'Hello', 'status' => 'published']);

    $html = Livewire::test(EsaStackedComponent::class)
        ->set('tableState.filters.status.value', 'draft')
        ->html();

    expect(substr_count($html, 'data-testid="table-filter-reset"'))->toBe(2)
        ->and($html)->not->toContain('Create post');
});

it('binds an empty state shortcut once across both layouts', function () {
    $html = Livewire::test(EsaStackedComponent::class)->html();

    // The shortcut binding is `x-on:keydown.<keys>.window.prevent="$el.click()"`.
    expect(substr_count($html, 'window.prevent="$el.click()"'))->toBe(1);
});

// ─── Execution ───────────────────────────────────────────────────

it('executes an empty state action without a record', function () {
    $test = Livewire::test(EsaComponent::class)
        ->call('executeHeaderAction', 'createInline');

    expect($test->instance()->ran)->toBe(['createInline']);
});

// Without findHeaderAction() searching the empty-state actions, this renders a
// button whose click resolves to nothing.
it('opens the modal of an empty state action', function () {
    $test = Livewire::test(EsaComponent::class)
        ->call('openHeaderActionModal', 'createModal');

    expect($test->instance()->tableState->get('modal.actions.0.name'))->toBe('createModal')
        ->and($test->instance()->tableState->get('modal.actions.0.isHeaderAction'))->toBeTrue()
        ->and($test->instance()->tableState->get('modal.actions.0.recordKey'))->toBeNull();
});

it('submits the modal of an empty state action', function () {
    $test = Livewire::test(EsaComponent::class)
        ->call('openHeaderActionModal', 'createModal')
        ->set('tableState.modal.actions.0.data.title', 'Written in the modal')
        ->call('submitActionModal');

    expect($test->instance()->ran)->toBe(['createModal']);
});

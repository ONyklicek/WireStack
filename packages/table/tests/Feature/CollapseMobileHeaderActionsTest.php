<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * collapseHeaderActionsOnMobile() folds the toolbar's header-action buttons into
 * a single ActionGroup dropdown below the mobile breakpoint. Both halves live in
 * the document at every width — CSS picks one — so this renders end to end and
 * checks what each half actually emits.
 */

class CollapseHeaderActionsUser extends Model
{
    protected $table = 'collapse_header_actions_users';

    protected $guarded = [];
}

class CollapseHeaderActionsHost extends Component
{
    use WithTable;

    public bool $collapse = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(CollapseHeaderActionsUser::class)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('create')->label('New user')->keyboardShortcut('c'),
                HeaderAction::make('import')->label('Import')->requiresConfirmation(),
            ])
            ->collapseHeaderActionsOnMobile($this->collapse)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class CollapseHeaderActionsSingleHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(CollapseHeaderActionsUser::class)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('create')->label('New user'),
                HeaderAction::make('import')->label('Import')->visible(false),
            ])
            ->collapseHeaderActionsOnMobile(threshold: 1)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('collapse_header_actions_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    CollapseHeaderActionsUser::create(['name' => 'Ada Lovelace']);
});

afterEach(fn () => Schema::dropIfExists('collapse_header_actions_users'));

it('renders header actions as plain toolbar buttons by default', function () {
    Livewire::test(CollapseHeaderActionsHost::class)
        ->assertSee('header-action-create', escape: false)
        ->assertSee('header-action-import', escape: false)
        // No dropdown, and nothing wrapping the buttons for a breakpoint.
        ->assertDontSee('table-header-actions-mobile', escape: false)
        ->assertDontSee('action-group-trigger', escape: false)
        ->assertDontSee('hidden sm:flex', escape: false);
});

it('collapses the header actions into one dropdown below the mobile breakpoint', function () {
    Livewire::test(CollapseHeaderActionsHost::class)
        ->set('collapse', true)
        // Desktop half: the same buttons, now hidden below the breakpoint.
        ->assertSee('hidden sm:flex', escape: false)
        ->assertSee('header-action-create', escape: false)
        // Mobile half: one trigger, shown only below the breakpoint.
        ->assertSee('table-header-actions-mobile', escape: false)
        ->assertSee('sm:hidden', escape: false)
        ->assertSee('action-group-trigger', escape: false)
        // …with both actions as menu rows wired to the record-less host methods.
        ->assertSee('menu-action-create', escape: false)
        ->assertSee('menu-action-import', escape: false)
        // (escaped: the expression sits in a wire:click attribute)
        ->assertSee("executeHeaderAction('create')")
        // The confirmation action opens the modal instead of running.
        ->assertSee("openHeaderActionModal('import')");
});

it('renders a lone surviving action as a button instead of a one-item menu', function () {
    Livewire::test(CollapseHeaderActionsSingleHost::class)
        // Collapse is on (threshold 1) but the viewer may run only one action,
        // so the extra tap of a dropdown would buy nothing.
        ->assertSee('table-header-actions-mobile', escape: false)
        ->assertSee('header-action-create', escape: false)
        ->assertDontSee('action-group-trigger', escape: false);
});

it('binds the header action keyboard shortcut once, on the button half only', function () {
    $html = Livewire::test(CollapseHeaderActionsHost::class)->set('collapse', true)->html();

    // A rendered shortcut is a *window* listener and both halves are in the
    // document at every width; a second binding would run the action twice on
    // one keypress.
    expect(substr_count($html, 'keydown.c.window'))->toBe(1);
});

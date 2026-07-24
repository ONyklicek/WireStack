<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class CtxRow extends Model
{
    protected $table = 'ctx_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class CtxComponent extends Component
{
    use WithTable;

    public bool $withMenu = true;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(CtxRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')]);

        return $this->withMenu
            ? $table->rowContextMenu([
                Action::make('edit')->label('Edit'),
                Action::make('delete')->label('Delete')->color('danger'),
            ])
            : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class CtxNoMenuComponent extends CtxComponent
{
    public bool $withMenu = false;
}

beforeEach(function () {
    Schema::create('ctx_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    CtxRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('ctx_rows'));

function ctxRecord(): CtxRow
{
    return (new CtxRow)->forceFill(['id' => 1, 'name' => 'Alpha']);
}

// ─── Fluent API ──────────────────────────────────────────────────

it('is off until dedicated actions are given', function () {
    expect(Table::make()->hasRowContextMenu())->toBeFalse()
        ->and(Table::make()->rowContextMenu([])->hasRowContextMenu())->toBeFalse();

    $table = Table::make()->rowContextMenu([Action::make('edit')]);
    expect($table->hasRowContextMenu())->toBeTrue()
        ->and($table->getRowContextMenuActions())->toHaveCount(1);
});

it('keeps the context-menu actions separate from the row actions', function () {
    $table = Table::make()
        ->actions([Action::make('edit')->label('Edit')])
        ->rowContextMenu([Action::make('archive')->label('Archive')]);

    // Only the dedicated menu actions render — not the toolbar actions.
    $html = $table->getRowContextMenuHtml(ctxRecord())->toHtml();
    expect($html)->toContain('Archive')->not->toContain('Edit');
});

// ─── Menu HTML ───────────────────────────────────────────────────

it('renders the menu actions as context-menu items', function () {
    $table = Table::make()->rowContextMenu([
        Action::make('edit')->label('Edit'),
        Action::make('delete')->label('Delete'),
    ]);

    $html = $table->getRowContextMenuHtml(ctxRecord())->toHtml();

    expect($html)->toContain('Edit')->toContain('Delete');
});

it('omits a hidden action from the context menu', function () {
    $table = Table::make()->rowContextMenu([
        Action::make('edit')->label('Edit'),
        Action::make('secret')->label('Secret')->visible(fn () => false),
    ]);

    $html = $table->getRowContextMenuHtml(ctxRecord())->toHtml();

    expect($html)->toContain('Edit')->not->toContain('Secret');
});

it('flattens an action group into the context menu items', function () {
    $table = Table::make()->rowContextMenu([
        ActionGroup::make([
            Action::make('edit')->label('Edit'),
            Action::make('archive')->label('Archive'),
        ]),
    ]);

    $html = $table->getRowContextMenuHtml(ctxRecord())->toHtml();

    expect($html)->toContain('Edit')->toContain('Archive');
});

it('returns empty html when no menu actions are configured', function () {
    expect(Table::make()->getRowContextMenuHtml(ctxRecord())->toHtml())->toBe('');
});

// ─── Render integration ──────────────────────────────────────────

it('wires the context menu into rows when enabled', function () {
    Livewire::test(CtxComponent::class)
        // The delegated controller owns the right-click, not a per-row component.
        ->assertSee('wireRecordActions', false)
        ->assertSee('onContextMenu($event)', false)
        ->assertSee('data-record-menu', false)
        ->assertDontSee('wireContextMenu', false);
});

it('does not wire the context menu when disabled', function () {
    Livewire::test(CtxNoMenuComponent::class)
        ->assertDontSee('wireRecordActions', false)
        ->assertDontSee('data-record-menu', false);
});

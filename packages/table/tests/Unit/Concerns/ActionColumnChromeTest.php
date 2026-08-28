<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The actions column's chrome — the half of HasTableActions that only ever
 * showed up in a browser.
 *
 * One `actionsAlignment()` call has to reach two different places with two
 * different vocabularies: the header <th> takes a `text-*` class, and the body
 * cell's flex row takes a `justify-*` one. Nothing checked that they agree, or
 * that either arrived at all — the getters were asserted in isolation, and the
 * compiled action cell {@see Table::getActionCellSkeleton()} had no test of any
 * kind. A cell that centred its buttons under a right-aligned header would have
 * passed every gate.
 *
 * Same for the column's density and border: the cell is compiled once for the
 * whole table rather than rendered per row, so `compact()` and `bordered()`
 * reach it through the skeleton or not at all.
 */

class ActionChromeRow extends Model
{
    protected $table = 'action_chrome_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ActionChromeHost extends Component
{
    use WithTable;

    public string $alignment = 'right';

    public bool $compact = false;

    public bool $bordered = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(ActionChromeRow::class)
            ->columns([TextColumn::make('name')])
            ->actions([Action::make('open')->label('Open')->action(fn () => null)])
            ->actionsAlignment($this->alignment)
            ->actionsColumnLabel('Řádkové akce')
            ->actionsColumnWidth('220px')
            ->compact($this->compact)
            ->bordered($this->bordered)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('action_chrome_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    ActionChromeRow::create(['name' => 'Ada']);
});

afterEach(fn () => Schema::dropIfExists('action_chrome_rows'));

/** The compiled cell as the row splices it, with one row's buttons in the hole. */
function actionCellFor(Table $table, string $actions = '<b>x</b>'): string
{
    return $table->getActionCellSkeleton()->fill(['actions' => $actions]);
}

it('leaves exactly one hole in the compiled action cell, for the row buttons', function () {
    $table = Table::make()->actions([Action::make('open')]);

    $skeleton = $table->getActionCellSkeleton();

    // The chrome is table-level and baked at compile time; only the buttons vary
    // per record, so there is one slot and the unfilled template still shows it.
    expect($skeleton->toHtml())->toContain(Skeleton::slot('actions'))
        ->and(actionCellFor($table, '<button>Open</button>'))
        ->toContain('<button>Open</button>')
        ->not->toContain(Skeleton::slot('actions'));
});

it('bakes the actions alignment into the cell as a literal justify class', function () {
    // Default, the two string tokens, and the enum — all landing on the flex row
    // that holds the buttons, which is the only thing that moves them.
    expect(actionCellFor(Table::make()))
        ->toContain('flex flex-wrap items-center gap-1 justify-end')
        ->and(actionCellFor(Table::make()->actionsAlignment('left')))
        ->toContain('flex flex-wrap items-center gap-1 justify-start')
        ->and(actionCellFor(Table::make()->actionsAlignment('center')))
        ->toContain('flex flex-wrap items-center gap-1 justify-center')
        ->and(actionCellFor(Table::make()->actionsAlignment(Alignment::Left)))
        ->toContain('flex flex-wrap items-center gap-1 justify-start');
});

it('bakes the table density and border into the cell', function () {
    expect(actionCellFor(Table::make()))
        ->toContain('px-6 py-4')
        ->not->toContain('border border-gray-200')
        ->and(actionCellFor(Table::make()->compact()))
        ->toContain('px-4 py-2')
        ->and(actionCellFor(Table::make()->bordered()))
        ->toContain('border border-gray-200 dark:border-gray-700');
});

it('compiles the action cell once per table', function () {
    $table = Table::make();

    // Memoised per instance — and the instance is per request, since Livewire
    // rebuilds the Table on every one, so nothing here can go stale.
    expect($table->getActionCellSkeleton())->toBe($table->getActionCellSkeleton())
        ->and(Table::make()->getActionCellSkeleton())->not->toBe($table->getActionCellSkeleton());
});

it('aligns the actions header and the action cells the same way', function (string $alignment, string $text, string $justify) {
    $html = Livewire::test(ActionChromeHost::class, ['alignment' => $alignment])->html();

    expect($html)
        // The header <th> takes the text-* vocabulary…
        ->toContain('font-semibold '.$text)
        // …and the body cell's flex row the justify-* one, from the same call.
        ->toContain('flex flex-wrap items-center gap-1 '.$justify);
})->with([
    ['left', 'text-left', 'justify-start'],
    ['center', 'text-center', 'justify-center'],
    ['right', 'text-right', 'justify-end'],
]);

it('gives the actions header its configured label and width', function () {
    $html = Livewire::test(ActionChromeHost::class)->html();

    expect($html)->toContain('Řádkové akce')
        ->toContain('width: 220px');
});

it('carries the table density and border through to the rendered action cell', function () {
    $plain = Livewire::test(ActionChromeHost::class)->html();
    $dense = Livewire::test(ActionChromeHost::class, ['compact' => true, 'bordered' => true])->html();

    expect($plain)->toContain('px-6 py-4 "><div class="flex flex-wrap items-center gap-1')
        ->and($dense)->toContain('px-4 py-2 border border-gray-200 dark:border-gray-700"><div class="flex flex-wrap items-center gap-1');
});

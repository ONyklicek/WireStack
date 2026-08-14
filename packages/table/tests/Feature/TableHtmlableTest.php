<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Support\IslandViewScope;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Exceptions\TableHasNoHostException;
use NyonCode\WireTable\Table;

/**
 * `{{ $table }}` — rule 3 of `AI_CODING_STANDARD.md`.
 *
 * > Components implement `Htmlable`. `{{ $component }}` must render without
 * > helpers.
 *
 * `Table` has declared `Htmlable` all along and did not honour it: `toHtml()`
 * rendered the table view with only `['table' => $this]`, leaving `$component`
 * and `$records` undefined, so echoing a table died on a method call against
 * null. Nothing exercised it — the relation manager renders `{{ $this->table }}`,
 * which is the component's computed property, not this method.
 */
class ThtRow extends Model
{
    protected $table = 'tht_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class ThtHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(ThtRow::class)
            ->columns([TextColumn::make('name')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('tht_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    ThtRow::create(['name' => 'Amelia']);
});

afterEach(fn () => Schema::dropIfExists('tht_rows'));

it('renders a hosted table when it is echoed', function () {
    $table = Livewire::test(ThtHost::class)->instance()->getTable();

    $html = $table->toHtml();

    expect($table)->toBeInstanceOf(Htmlable::class)
        ->and($html)->toContain('Amelia')
        ->and($html)->toContain('<table');
});

it('renders the same markup through Blade as through the method', function () {
    // The rule is about `{{ $table }}`, so the echo is what has to be asserted —
    // Blade calls toHtml() through Htmlable rather than casting to a string.
    $table = Livewire::test(ThtHost::class)->instance()->getTable();

    expect(Blade::render('{{ $table }}', ['table' => $table]))->toBe($table->toHtml());
});

it('renders through the host, so it honours the host view', function () {
    // The direct view() call this replaced hardcoded wire-table::tables.index,
    // which would have skipped a host that overrides getTableView() — such as
    // wire-sortable's wrapper, which is what mounts the drag controller.
    $component = Livewire::test(ThtHost::class)->instance();

    // Compared against the host's view rendered WITH the component in view scope,
    // which is what toHtml() now does and what a Livewire render does for free.
    // A naked ->render() is no longer the same thing: the rows live in an
    // `@island`, and `@island` compiles to a directive guarded on `$__livewire`,
    // so without that scope it emits nothing at all — chrome, and no rows.
    $scoped = IslandViewScope::within(
        $component,
        fn (): string => $component->getTableProperty()->render(),
    );

    expect($component->getTable()->toHtml())->toBe($scoped)
        ->and($scoped)->toContain('<table');
});

it('refuses to render a table that has no host, and says why', function () {
    // A definition cannot produce a render: the state, the page of records and
    // the component id every client binding is scoped to all live on the host.
    // The old code reached the same conclusion by dying on a null method call.
    Table::make()->columns([TextColumn::make('name')])->toHtml();
})->throws(TableHasNoHostException::class, 'can only render through its Livewire host');

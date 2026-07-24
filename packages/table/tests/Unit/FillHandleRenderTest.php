<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * What the fill handle puts in the DOM.
 *
 * The markup is deliberately one element per table rather than one per cell, so
 * the interesting assertions are that it appears exactly once at any row count,
 * that it appears only when the table opted in, and that the column allowlist
 * honours ->fillable(false).
 */

class FhrRow extends Model
{
    protected $table = 'fhr_rows';

    protected $guarded = [];
}

/** Fill on. No filters, no toggleable columns, no context menu — see the asset test. */
class FhrComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhrRow::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([
                TextInputColumn::make('status'),
                TextInputColumn::make('code')->fillable(false),
                TextColumn::make('name'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** The default: no fill handle. */
class FhrDisabledComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhrRow::class)
            ->paginated(false)
            ->columns([TextInputColumn::make('status')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Opted in, but nothing is fillable — there is nothing to drag. */
class FhrNothingFillableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhrRow::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([TextColumn::make('name'), TextInputColumn::make('code')->fillable(false)]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function fhrSeed(int $rows): void
{
    $now = now();

    FhrRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'status' => 'open',
        'code' => 'c'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

beforeEach(function () {
    Schema::create('fhr_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('open');
        $table->string('code')->default('');
        $table->timestamps();
    });

    fhrSeed(3);
});

afterEach(fn () => Schema::dropIfExists('fhr_rows'));

// ─── Presence ────────────────────────────────────────────────────────────────

it('renders the handle, the range overlay and the positioning root when opted in', function () {
    $html = Livewire::test(FhrComponent::class)->html();

    expect($html)->toContain('data-testid="table-fill-handle"')
        ->and($html)->toContain('data-testid="table-fill-overlay"')
        ->and($html)->toContain('data-fill-root')
        ->and($html)->toContain('data-fill-max="500"');
});

// Icon-only control: without a label it is an unnamed button to a screen reader.
// The title carries the same text as a pointer tooltip.
it('names the handle for assistive technology and on hover', function () {
    $label = __('wire-table::messages.fill_handle');

    expect(Livewire::test(FhrComponent::class)->html())
        ->toContain('aria-label="'.$label.'"')
        ->toContain('title="'.$label.'"');
});

// Rendering rule: a framework path emits icons through icon(), never as a
// hand-written <svg> and never through <x-wire::icon>.
it('renders the copy icon from the canonical icon owner', function () {
    $html = Livewire::test(FhrComponent::class)->html();

    preg_match('/<button[^>]*data-testid="table-fill-handle"[^>]*>(.*?)<\/button>/s', $html, $m);

    expect($m[1] ?? '')->toContain('<svg')
        ->and($m[1] ?? '')->toContain('wire-fill-handle-icon');
});

// Until JS positions it, the handle has no cell to sit on — showing it first
// would park a stray square in the table's top-left corner on every load.
it('starts hidden, so it cannot flash before JS places it', function () {
    $html = Livewire::test(FhrComponent::class)->html();

    // Match the two elements themselves — the page is full of Tailwind's own
    // `hidden` class, so counting the word would assert nothing.
    preg_match('/<button[^>]*data-testid="table-fill-handle"[^>]*>/', $html, $handle);
    preg_match('/<div[^>]*data-testid="table-fill-overlay"[^>]*>/', $html, $overlay);

    expect($handle[0] ?? '')->toContain('hidden')
        ->and($overlay[0] ?? '')->toContain('hidden');
});

it('lists only the columns a fill may write', function () {
    $html = Livewire::test(FhrComponent::class)->html();

    preg_match('/data-fill-columns="([^"]*)"/', $html, $m);
    $columns = json_decode(html_entity_decode($m[1] ?? '[]'), true);

    // 'code' opted out; 'name' is not editable at all.
    expect($columns)->toBe(['status']);
});

// ─── Absence ─────────────────────────────────────────────────────────────────

it('renders nothing at all when the table did not opt in', function () {
    $html = Livewire::test(FhrDisabledComponent::class)->html();

    expect($html)->not->toContain('data-fill-handle')
        ->and($html)->not->toContain('data-fill-root')
        ->and($html)->not->toContain('table-fill-overlay');
});

it('renders nothing when no visible column is fillable', function () {
    $html = Livewire::test(FhrNothingFillableComponent::class)->html();

    expect($html)->not->toContain('data-fill-handle')
        ->and($html)->not->toContain('data-fill-root');
});

// ─── One per table ───────────────────────────────────────────────────────────

it('renders exactly one handle no matter how many rows there are', function () {
    expect(substr_count(Livewire::test(FhrComponent::class)->html(), 'data-testid="table-fill-handle"'))
        ->toBe(1);

    fhrSeed(20); // 3 → 23 rows

    expect(substr_count(Livewire::test(FhrComponent::class)->html(), 'data-testid="table-fill-handle"'))
        ->toBe(1);
});

// ─── The JS bundle ───────────────────────────────────────────────────────────

// The scaffolding partial used to be pulled in only by a filter, a toggleable
// column or a row context menu. A table with editable columns and none of those
// three shipped no JS at all — so the fill handle would have rendered and stayed
// dead. Fill now pulls it in itself.
it('ships the Alpine bundle for a table that has no other reason to load it', function () {
    $renders = 0;
    View::composer('wire-core::partials.floating-assets', function () use (&$renders): void {
        $renders++;
    });

    Livewire::test(FhrComponent::class)->html();

    expect($renders)->toBeGreaterThan(0);
});

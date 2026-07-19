<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\ButtonColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Per-row primitive cost fuse (render-engine-htmlable-first.md §3).
 *
 * Atomic primitives (spinner, success check) are byte-identical across rows, so
 * `@include`ing their Blade partial per row is pure N×View waste. After §3 they are
 * resolved once and reused as a cached string, so a primitive contributes **zero**
 * view renders that grow with the row count. This asserts exactly that: the per-row
 * view-render cost of a button / editable column is constant in the primitive, i.e.
 * the growth-per-row equals the one cell partial, not the cell + its primitives.
 *
 * Counting works the same way as the §1 fuse: a wildcard view composer sees every
 * view instance rendered, including every `@include` and `<x-…>` component.
 */

// ─── Model + components ──────────────────────────────────────────────────────

class PrimRow extends Model
{
    protected $table = 'prim_rows';

    protected $guarded = [];
}

class PrimButtonComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(PrimRow::class)
            ->paginated(false)
            ->columns([
                // A button (not a link) shows the loading spinner branch.
                ButtonColumn::make('act')->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class PrimEditableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(PrimRow::class)
            ->paginated(false)
            ->columns([
                // Editable cell carries both the saving spinner and the success check.
                TextInputColumn::make('name'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class PrimActionComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(PrimRow::class)
            ->paginated(false)
            ->columns([ButtonColumn::make('name')->action(fn () => null)])
            ->actions([
                // A per-row action whose loading spinner is now a cached string.
                Action::make('do')->action(fn () => null)->loadingIndicator(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function primRenderCount(Closure $render): int
{
    $count = 0;

    View::composer('*', function () use (&$count): void {
        $count++;
    });

    $render();

    return $count;
}

function primSeed(int $rows): void
{
    $now = now();

    PrimRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

/**
 * View renders attributable to one row of the given component: the slope of the
 * render count against the row count, isolated from fixed header/footer chrome.
 *
 * A warm-up render is done first, on purpose: primitives are resolved once per
 * request and memoised on the `Primitives` singleton, so their one-time render is an
 * O(1) cost, not O(rows). Warming it before both measurements keeps that one-time
 * cost out of the slope — which is exactly the property under test: primitives must
 * not add a *per-row* render.
 */
function primPerRow(string $component): int
{
    primSeed(4);
    Livewire::test($component)->html(); // warm once-per-request primitive caches

    $small = primRenderCount(fn () => Livewire::test($component)->html());

    primSeed(8); // 4 → 12
    $large = primRenderCount(fn () => Livewire::test($component)->html());

    $growth = $large - $small;

    expect($growth % 8)->toBe(0); // strictly linear — no per-row constant hiding

    return intdiv($growth, 8);
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('prim_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('prim_rows');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('renders a button cell with no per-row primitive view renders', function () {
    // One cell partial (tables.columns.button) per row. The loading spinner must
    // NOT add a per-row view render — it is a cached string, not an @include.
    expect(primPerRow(PrimButtonComponent::class))->toBe(1);
});

it('renders an editable cell with no per-row primitive view renders', function () {
    // One cell partial (tables.columns.text-input-editable) per row. Neither the
    // saving spinner nor the success check may add a per-row view render.
    expect(primPerRow(PrimEditableComponent::class))->toBe(1);
});

it('still emits the primitive markup it now resolves as a cached string', function () {
    // Guard the other half: fewer renders must not mean an empty cell. The cached
    // strings have to actually reach the DOM.
    primSeed(2);

    $button = Livewire::test(PrimButtonComponent::class)->html();
    expect($button)->toContain('animate-spin'); // spinner reached the button

    $editable = Livewire::test(PrimEditableComponent::class)->html();
    expect($editable)->toContain('animate-spin')       // saving spinner
        ->and($editable)->toContain('text-green-500');  // success check
});

it('routes the row-action spinner through the cached primitive, gated per record', function () {
    primSeed(2);

    $html = Livewire::test(PrimActionComponent::class)->html();

    // The formerly hardcoded <svg> is now the canonical spinner string, wrapped in a
    // span that carries the per-record wire:target.
    expect($html)->toContain('animate-spin')
        ->and($html)->toContain('wire:target="executeTableAction(');
});

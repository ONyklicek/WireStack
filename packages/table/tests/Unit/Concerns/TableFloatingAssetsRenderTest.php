<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Floating-assets emit fuse (render-engine-htmlable-first.md §4).
 *
 * The dropdown-JS scaffolding partial was `@include`d once per action-group dropdown
 * per row — and twice, since the desktop table and the mobile card layout both
 * render server-side — recomputing `route()` each time. After §4 the URL is resolved
 * once per request by a memoised owner and the scaffolding emits once via `@once`.
 * This counts renders of the floating-assets partial specifically (a targeted view
 * composer) and asserts it does not scale with the row count.
 */

// ─── Model + component ───────────────────────────────────────────────────────

class FaRow extends Model
{
    protected $table = 'fa_rows';

    protected $guarded = [];
}

class FaComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FaRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
                // Two actions collapse into a dropdown, which pulls in floating-assets.
                ActionGroup::make([
                    Action::make('edit')->action(fn () => null),
                    Action::make('delete')->action(fn () => null),
                ]),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class FaContextMenuComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FaRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            // A per-row right-click menu also pulls floating-assets into the row loop.
            ->rowContextMenu([
                Action::make('edit')->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function faFloatingAssetRenders(Closure $render): int
{
    $count = 0;

    View::composer('wire-core::partials.floating-assets', function () use (&$count): void {
        $count++;
    });

    $render();

    return $count;
}

function faSeed(int $rows): void
{
    $now = now();

    FaRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('fa_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('fa_rows');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('adds no floating-assets render per row', function () {
    faSeed(3);
    $few = faFloatingAssetRenders(fn () => Livewire::test(FaComponent::class)->html());

    faSeed(9); // 3 → 12 rows
    $many = faFloatingAssetRenders(fn () => Livewire::test(FaComponent::class)->html());

    // The whole point of §4: the per-row action-group dropdown no longer re-emits
    // the scaffolding. Quadrupling the rows adds exactly zero floating-assets
    // renders — O(1), down from the pre-@once O(rows).
    expect($many - $few)->toBe(0);
});

it('still emits the dropdown scaffolding for the table', function () {
    faSeed(3);

    // Fewer emits must not mean none: the action-group dropdown still needs its JS.
    expect(faFloatingAssetRenders(fn () => Livewire::test(FaComponent::class)->html()))
        ->toBeGreaterThan(0);
});

it('adds no floating-assets render per row for a row context menu either', function () {
    faSeed(3);
    $few = faFloatingAssetRenders(fn () => Livewire::test(FaContextMenuComponent::class)->html());

    faSeed(9); // 3 → 12 rows
    $many = faFloatingAssetRenders(fn () => Livewire::test(FaContextMenuComponent::class)->html());

    expect($many - $few)->toBe(0)
        ->and($few)->toBeGreaterThan(0);
});

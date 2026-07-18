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
 * Lazy-menu render fuse (render-engine-htmlable-first.md §6).
 *
 * An eager action-group dropdown renders one `dropdown-item` Blade view per action
 * per row. ActionGroup::lazyMenu() ships a serialized spec instead and builds the
 * menu client-side, so the per-row `dropdown-item` render count drops to zero. This
 * counts renders of that view specifically for an eager vs a lazy group.
 */

// ─── Model + components ──────────────────────────────────────────────────────

class LazyRow extends Model
{
    protected $table = 'lazy_rows';

    protected $guarded = [];
}

class LazyEagerComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(LazyRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
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

class LazyMenuComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(LazyRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
                ActionGroup::make([
                    Action::make('edit')->action(fn () => null),
                    Action::make('delete')->action(fn () => null),
                ])->lazyMenu(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function lazyDropdownItemRenders(string $component): int
{
    $count = 0;

    View::composer('wire-core::actions.dropdown-item', function () use (&$count): void {
        $count++;
    });

    Livewire::test($component)->html();

    return $count;
}

function lazySeed(int $rows): void
{
    $now = now();

    LazyRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i,
        'created_at' => $now,
        'updated_at' => $now,
    ], range(1, $rows)));
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('lazy_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('lazy_rows');
});

// ─── The fuse ────────────────────────────────────────────────────────────────

it('renders zero dropdown-item views for a lazy menu', function () {
    lazySeed(4);

    expect(lazyDropdownItemRenders(LazyMenuComponent::class))->toBe(0);
});

it('renders dropdown-item views eagerly without lazyMenu (the baseline it fixes)', function () {
    lazySeed(4);

    // Proves the fuse is live: the eager group DOES render menu items per row, so a
    // zero for the lazy group is a real difference, not an empty table.
    expect(lazyDropdownItemRenders(LazyEagerComponent::class))->toBeGreaterThan(0);
});

it('still ships the menu items as a client spec when lazy', function () {
    lazySeed(2);

    $html = Livewire::test(LazyMenuComponent::class)->html();

    // The row carries the serialized items (for client render) and the trigger,
    // even though it rendered no dropdown-item view.
    expect($html)->toContain('menu-action-edit')
        ->and($html)->toContain('data-testid="action-group-trigger"');
});

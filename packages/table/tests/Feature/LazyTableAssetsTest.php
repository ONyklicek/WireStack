<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A lazy() table ships its Alpine bundles with the DEFERRED render, not with the
 * placeholder.
 *
 * This is the inverse of what it used to assert. Each bundle registered its
 * components from an `alpine:init` listener, and that event fires once, when
 * Alpine boots — so a bundle first emitted by the deferred render subscribed to
 * an event that would never fire again and registered nothing. The placeholder
 * render was therefore forced to pre-emit all three.
 *
 * The bundles now register unconditionally, and Livewire awaits
 * `payload.intercept` — which loads and runs the new @assets to completion —
 * before `handleSuccess` morphs the markup in, so the factory exists before the
 * deferred table is initialised. Pre-emitting is not just unnecessary, it is
 * wrong: it pulls bundles onto a page whose whole point is to defer them.
 *
 * Asserted by counting renders of the asset partials rather than by scanning the
 * HTML: @assets is hoisted out of the component markup, so assertSee() cannot
 * see it.
 */

class LazyAssetsUser extends Model
{
    protected $table = 'lazy_assets_users';

    protected $guarded = [];
}

class LazyAssetsHost extends Component
{
    use WithTable;

    public bool $selectable = true;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(LazyAssetsUser::class)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('edit')->label('Edit'),
                Action::make('delete')->label('Delete'),
            ])
            ->stackedOnMobile()
            ->collapseActionsOnMobile(threshold: 1)
            ->lazy()
            ->paginated(false);

        return $this->selectable ? $table->selectable() : $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/**
 * How many times each asset partial rendered while $render ran.
 *
 * @return array{dropdown: int, selection: int}
 */
function lazyAssetRenders(Closure $render): array
{
    $counts = ['dropdown' => 0, 'selection' => 0];

    View::composer('wire-core::partials.floating-assets', function () use (&$counts): void {
        $counts['dropdown']++;
    });
    View::composer('wire-table::tables.partials.selection-assets', function () use (&$counts): void {
        $counts['selection']++;
    });

    $render();

    return $counts;
}

beforeEach(function () {
    Schema::create('lazy_assets_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    LazyAssetsUser::create(['name' => 'Ada Lovelace']);
});

afterEach(fn () => Schema::dropIfExists('lazy_assets_users'));

it('keeps the bundles off the lazy placeholder', function () {
    $counts = lazyAssetRenders(function () {
        Livewire::test(LazyAssetsHost::class)
            // Still the placeholder: the table body has not been loaded yet.
            ->assertSee('table-lazy-wrapper', escape: false)
            ->assertDontSee('table-card', escape: false);
    });

    // A deferred table defers its bundles too. This is the assertion that used to
    // read `toBe(1)` — see the note at the top of the file for why it flipped.
    expect($counts['dropdown'])->toBe(0)
        ->and($counts['selection'])->toBe(0);
});

it('ships the bundles with the deferred render that swaps the table in', function () {
    $counts = lazyAssetRenders(function () {
        Livewire::test(LazyAssetsHost::class)
            ->call('loadTable')
            ->assertSee('table-card', escape: false);
    });

    // Livewire loads and runs these to completion during `payload.intercept`,
    // before `handleSuccess` morphs this markup in, so the factories exist by the
    // time Alpine initialises the table.
    expect($counts['dropdown'])->toBeGreaterThan(0)
        ->and($counts['selection'])->toBeGreaterThan(0);
});

it('omits the selection bundle when the loaded table is not selectable', function () {
    $counts = lazyAssetRenders(function () {
        Livewire::test(LazyAssetsHost::class, ['selectable' => false])
            ->call('loadTable')
            ->assertSee('table-card', escape: false);
    });

    expect($counts['dropdown'])->toBeGreaterThan(0)
        ->and($counts['selection'])->toBe(0);
});

it('still renders the collapsed action group once the lazy table loads', function () {
    Livewire::test(LazyAssetsHost::class)
        ->call('loadTable')
        ->assertSee('table-card', escape: false)
        ->assertSee('action-group-trigger', escape: false);
});

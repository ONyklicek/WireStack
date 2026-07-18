<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Hot-path icon fuse (render-optimization-audit-2026-07-17.md Tier 1).
 *
 * Core render partials emit icons through the `@icon` directive (a memoised
 * IconManager string) instead of `<x-wire::icon>` (a Blade component = one view
 * render). So the per-row selection check and the copyable clipboard/check icons no
 * longer render the `wire-core::foundation.icon` view. This counts renders of that
 * view specifically and asserts the count does not grow with the row count.
 */
class IconRow extends Model
{
    protected $table = 'icon_rows';

    protected $guarded = [];
}

class IconComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(IconRow::class)
            ->paginated(false)
            ->selectable() // per-row select check icon
            ->columns([
                TextColumn::make('name')->copyable(), // per-cell clipboard + check icons
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function iconComponentRenders(Closure $render): int
{
    $count = 0;
    View::composer('wire-core::foundation.icon', function () use (&$count): void {
        $count++;
    });
    $render();

    return $count;
}

function iconSeed(int $rows): void
{
    $now = now();
    IconRow::insert(array_map(fn (int $i) => [
        'name' => 'row-'.$i, 'created_at' => $now, 'updated_at' => $now,
    ], range(1, $rows)));
}

beforeEach(function () {
    Schema::create('icon_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(fn () => Schema::dropIfExists('icon_rows'));

it('renders no per-row icon component (selection + copyable go through @icon)', function () {
    iconSeed(3);
    $few = iconComponentRenders(fn () => Livewire::test(IconComponent::class)->html());

    iconSeed(6); // 3 → 9 rows
    $many = iconComponentRenders(fn () => Livewire::test(IconComponent::class)->html());

    // The hot-path icons (row-select check ×R, copyable clipboard+check ×R) used to
    // render the foundation.icon component per row; now they are @icon strings, so
    // tripling the rows adds zero icon-component renders.
    expect($many - $few)->toBe(0);
});

it('still emits the migrated icons into the DOM', function () {
    iconSeed(1);

    $html = Livewire::test(IconComponent::class)->html();

    // The copyable clipboard SVG and the row-select check reach the row markup as
    // real <svg> (via @icon), not as an unrendered component.
    expect($html)->toContain('data-testid="cell-copy"')
        ->and(substr_count($html, '<svg'))->toBeGreaterThan(0);
});

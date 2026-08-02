<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * extraAttributes() and extraHeaderAttributes() stored their value and nothing
 * ever read it — the table view rendered neither, so both setters were silent
 * no-ops on every column type.
 */

class ExtraAttrRow extends Model
{
    protected $table = 'extra_attr_rows';

    protected $guarded = [];
}

class ExtraAttrHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(ExtraAttrRow::class)
            ->columns([
                TextColumn::make('name')
                    ->extraAttributes('data-cell-role="name" title="A name"')
                    ->extraHeaderAttributes(['data-head-role' => 'name', 'aria-description' => 'The name']),
                TextColumn::make('note'),
            ])
            ->searchable(false)
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::dropIfExists('extra_attr_rows');
    Schema::create('extra_attr_rows', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('note')->nullable();
        $table->timestamps();
    });

    ExtraAttrRow::query()->create(['name' => 'Ada', 'note' => 'first']);
    ExtraAttrRow::query()->create(['name' => 'Grace', 'note' => 'second']);
});

it('puts the raw cell attributes on every cell of that column', function () {
    $html = Livewire::test(ExtraAttrHost::class)->html();

    // One per rendered row, and only on the column that asked for them.
    expect(substr_count($html, 'data-cell-role="name"'))->toBe(2)
        ->and($html)->toContain('title="A name"');
});

it('puts the header attributes on that column header only', function () {
    $html = Livewire::test(ExtraAttrHost::class)->html();

    expect(substr_count($html, 'data-head-role="name"'))->toBe(1)
        ->and($html)->toContain('aria-description="The name"');
});

it('leaves a column that asked for nothing untouched', function () {
    $html = Livewire::test(ExtraAttrHost::class)->html();

    // The note column's cells carry no stray attributes from its neighbour.
    expect(substr_count($html, 'data-column="note"'))->toBeGreaterThan(0)
        ->and($html)->not->toContain('data-cell-role="note"');
});

it('escapes header attribute values', function () {
    $column = TextColumn::make('name')->extraHeaderAttributes(['data-x' => 'a" onmouseover="alert(1)']);

    expect($column->getExtraHeaderAttributes())->toBe(['data-x' => 'a" onmouseover="alert(1)']);

    // The view escapes on the way out; the getter keeps the author's value.
    $escaped = collect($column->getExtraHeaderAttributes())
        ->map(fn ($v, $k) => e($k).'="'.e($v).'"')
        ->implode(' ');

    expect($escaped)->not->toContain('onmouseover="alert(1)"')
        ->and($escaped)->toContain('&quot;');
});

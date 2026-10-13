<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\SplitColumn;
use NyonCode\WireTable\Columns\TextColumn;

class SplitColumnRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function splitRecord(array $attributes = []): SplitColumnRecord
{
    return new SplitColumnRecord($attributes + [
        'id' => 1,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'avatar' => 'ada.png',
    ]);
}

// ─── Composition ───────────────────────────────────────────────────

test('split() builds the column with its children in one call', function () {
    $column = SplitColumn::split([
        TextColumn::make('name'),
        TextColumn::make('email'),
    ], 'identity');

    expect($column->getName())->toBe('identity')
        ->and($column->getColumns())->toHaveCount(2)
        ->and($column->getColumns()[0]->getName())->toBe('name');
});

test('make() starts empty and columns() fills it', function () {
    $column = SplitColumn::make()->columns([TextColumn::make('name')]);

    expect($column->getName())->toBe('split')
        ->and($column->getColumns())->toHaveCount(1);
});

// ─── Layout ────────────────────────────────────────────────────────

test('horizontal layout carries the gap and the cross-axis alignment', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->gap('6')
        ->alignStart();

    expect($column->renderCell(splitRecord()))
        ->toContain('gap-6')
        ->toContain('items-start');
});

test('alignCenter() puts the default back', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->alignStart()
        ->alignCenter();

    expect($column->renderCell(splitRecord()))->toContain('items-center');
});

// A column stretches its children unless told otherwise, and that is what it
// always did — the vertical branch simply never printed the class. So an unset
// alignment must keep rendering nothing, or every existing vertical split moves.
test('vertical layout stacks without an alignment until one is asked for', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->vertical()
        ->gap('6');

    expect($column->renderCell(splitRecord()))
        ->toContain('flex-col')
        ->toContain('gap-6')
        ->not->toContain('items-');
});

test('an explicit alignment reaches a vertical split', function () {
    $start = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->vertical()
        ->alignStart();

    $centre = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->vertical()
        ->alignCenter();

    expect($start->renderCell(splitRecord()))->toContain('items-start')
        ->and($centre->renderCell(splitRecord()))->toContain('items-center');
});

// ─── Gap ───────────────────────────────────────────────────────────

// The class used to be built as "gap-$this->gap", which Tailwind's scanner never
// reads — so every value rendered with no gap unless some other file happened to
// spell the same utility out. GapScale owns the literal now, and the named sizes
// the page has always documented resolve for the first time.
test('a named gap resolves to a literal utility', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')->gap('sm');

    expect($column->getGapClass())->toBe('gap-2');
});

test('a numeric gap is taken as a step, as an int or a string', function () {
    expect(SplitColumn::make()->gap(6)->getGapClass())->toBe('gap-6')
        ->and(SplitColumn::make()->gap('6')->getGapClass())->toBe('gap-6');
});

test('an unrecognised gap falls back to the split default rather than a dead class', function () {
    expect(SplitColumn::make()->gap('enormous')->getGapClass())->toBe('gap-3');
});

test('a split with no gap set sits tighter than a page-level Flex', function () {
    expect(SplitColumn::make()->getGapClass())->toBe('gap-3');
});

test('horizontal() returns a vertical split to a row', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->vertical()
        ->horizontal();

    expect($column->renderCell(splitRecord()))->not->toContain('flex-col');
});

test('an image child is rendered apart from the text children', function () {
    $column = SplitColumn::split([
        ImageColumn::make('avatar'),
        TextColumn::make('name'),
    ], 'identity');

    $html = $column->renderCell(splitRecord());

    expect($html)->toContain('Ada Lovelace')
        ->and($html)->toContain('ada.png');
});

// A child's markup is often bare text, and bare text dropped into a flex
// container is one anonymous flex item — two stacked amounts printed as
// "500 Kč1 000 Kč" on a single line. Each child now gets a box of its own.
test('a vertical split gives every child a box of its own', function () {
    $column = SplitColumn::split([
        TextColumn::make('name'),
        TextColumn::make('email'),
    ], 'identity')->vertical();

    expect($column->renderCell(splitRecord()))
        ->toContain('<div class="">Ada Lovelace</div><div class="">ada@example.com</div>');
});

test('the text beside an image stacks instead of running together', function () {
    $html = SplitColumn::split([
        ImageColumn::make('avatar'),
        TextColumn::make('name'),
        TextColumn::make('email'),
    ], 'identity')->renderCell(splitRecord());

    expect($html)->toContain('<div class="">Ada Lovelace</div><div class="">ada@example.com</div>');
});

// The row branch used to merge an empty image slot into its children, which
// put an empty flex item — and so a gap — in front of the first value.
test('a row without an image has no empty leading box', function () {
    $html = SplitColumn::split([TextColumn::make('name')], 'identity')->renderCell(splitRecord());

    expect($html)->not->toContain('<div></div>')
        ->and($html)->toContain('<div class="min-w-0 ">Ada Lovelace</div>');
});

test('a child that renders nothing takes no place in the split', function () {
    $html = SplitColumn::split([
        TextColumn::make('name')->visibleForRecord(fn () => false),
        TextColumn::make('email'),
    ], 'identity')->vertical()->renderCell(splitRecord());

    expect(substr_count($html, '<div class="'))->toBe(2);
});

// A child's alignment normally sits on its <td>, which a child inside a split
// never gets.
test('a child keeps its own alignment, and an unset one inherits the split', function () {
    $html = SplitColumn::split([
        TextColumn::make('name')->alignRight(),
        TextColumn::make('email'),
    ], 'identity')->vertical()->renderCell(splitRecord());

    expect($html)->toContain('<div class="text-right">Ada Lovelace</div>')
        ->and($html)->toContain('<div class="">ada@example.com</div>');
});

test('a row follows the split\'s own text alignment on its main axis', function () {
    $right = SplitColumn::split([TextColumn::make('name')], 'identity')->alignRight();

    expect($right->getJustifyClass())->toBe('justify-end')
        ->and($right->renderCell(splitRecord()))->toContain('justify-end')
        ->and(SplitColumn::make()->getJustifyClass())->toBe('')
        ->and($right->vertical()->getJustifyClass())->toBe('');
});

test('a hidden split renders nothing at all', function () {
    $column = SplitColumn::split([TextColumn::make('name')], 'identity')
        ->visibleForRecord(fn () => false);

    expect($column->renderCell(splitRecord()))->toBe('');
});

// ─── Searching ─────────────────────────────────────────────────────

test('the split is searchable when any child is, and names those children', function () {
    $column = SplitColumn::split([
        TextColumn::make('name')->searchable(),
        TextColumn::make('email'),
    ], 'identity');

    expect($column->isSearchable())->toBeTrue()
        ->and($column->getSearchColumns())->toBe(['name']);
});

test('a split with no searchable child falls back to its own capability', function () {
    $column = SplitColumn::split([TextColumn::make('email')], 'identity');

    expect($column->isSearchable())->toBeFalse()
        ->and($column->getSearchColumns())->toBe([]);

    expect($column->searchable()->isSearchable())->toBeTrue();
});

// The query planner asks the capability, not isSearchable() — the split has to
// answer there too, or the search box never reaches its children.
test('the split carries the capabilities its children have', function () {
    $column = SplitColumn::split([
        TextColumn::make('name')->searchable(),
        TextColumn::make('email')->sortable(),
    ], 'identity');

    expect($column->hasCapability(Capability::Searchable))->toBeTrue()
        ->and($column->hasCapability(Capability::Sortable))->toBeTrue()
        ->and(SplitColumn::split([TextColumn::make('name')], 'identity')->hasCapability(Capability::Searchable))->toBeFalse();
});

test('a child\'s explicit search columns are what the split searches', function () {
    $column = SplitColumn::split([
        TextColumn::make('full_name')->searchable(['first_name', 'last_name']),
        TextColumn::make('email')->searchable(),
    ], 'identity');

    expect($column->getSearchColumns())->toBe(['first_name', 'last_name', 'email']);
});

// ─── Sorting ───────────────────────────────────────────────────────

// The header is registered under the split's name ('identity'), which is a label
// for the group and not an attribute of the model. getSortColumn() is what keeps
// the two apart; TableQueryService asks it instead of reusing the clicked name.
test('the split sorts by its first sortable child', function () {
    $column = SplitColumn::split([
        TextColumn::make('avatar'),
        TextColumn::make('name')->sortable(),
        TextColumn::make('email')->sortable(),
    ], 'identity');

    expect($column->isSortable())->toBeTrue()
        ->and($column->getSortColumn())->toBe('name');
});

test('a split with no sortable child falls back to its own name', function () {
    $column = SplitColumn::split([TextColumn::make('email')], 'name');

    expect($column->isSortable())->toBeFalse()
        ->and($column->getSortColumn())->toBe('name');

    expect($column->sortable()->isSortable())->toBeTrue();
});

test('an ordinary column sorts by itself', function () {
    expect(Column::make('created_at')->sortable()->getSortColumn())->toBe('created_at');
});

test('a dotted relation path survives as the sort target', function () {
    expect(Column::make('company.name')->sortable()->getSortColumn())->toBe('company.name');
});

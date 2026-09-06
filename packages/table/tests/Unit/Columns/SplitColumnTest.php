<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
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

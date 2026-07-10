<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\ButtonColumn;
use NyonCode\WireTable\Columns\TextColumn;

function vfrRecord(array $attributes = []): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes + ['id' => 1]);

    return $record;
}

// ─── isVisibleForRecord (F1) ────────────────────────────────────────────────

it('is visible for every record by default', function () {
    $column = TextColumn::make('salary');

    expect($column->isVisibleForRecord(vfrRecord()))->toBeTrue();
});

it('redacts a cell per record via visibleForRecord()', function () {
    $column = TextColumn::make('salary')
        ->visibleForRecord(fn (Model $record) => $record->department === 'eng');

    $eng = vfrRecord(['salary' => 5000, 'department' => 'eng']);
    $sales = vfrRecord(['salary' => 9000, 'department' => 'sales']);

    // Structural presence is unaffected — the column still exists in the table.
    expect($column->canView())->toBeTrue()
        ->and($column->isVisibleForRecord($eng))->toBeTrue()
        ->and($column->isVisibleForRecord($sales))->toBeFalse();

    // renderCell redacts the hidden row's cell but renders the allowed one.
    expect($column->renderCell($eng))->toContain('5000')
        ->and($column->renderCell($sales))->toBe('');
});

it('passes the column as the second callback argument', function () {
    $seen = null;
    $column = TextColumn::make('x')->visibleForRecord(function (Model $record, $col) use (&$seen) {
        $seen = $col;

        return true;
    });

    $column->isVisibleForRecord(vfrRecord());

    expect($seen)->toBe($column);
});

it('applies to non-text columns too (BadgeColumn)', function () {
    $column = BadgeColumn::make('status')->visibleForRecord(fn (Model $r) => $r->id === 2);

    expect($column->renderCell(vfrRecord(['id' => 1, 'status' => 'active'])))->toBe('')
        ->and($column->renderCell(vfrRecord(['id' => 2, 'status' => 'active'])))->not->toBe('');
});

// ─── ButtonColumn::visibleWhen() is now a BC alias for visibleForRecord() ────

it('ButtonColumn visibleWhen() delegates to the canonical visibleForRecord()', function () {
    $column = ButtonColumn::make('action')
        ->label('Go')
        ->visibleWhen(fn (Model $record) => $record->id === 7);

    expect($column->isVisibleForRecord(vfrRecord(['id' => 7])))->toBeTrue()
        ->and($column->isVisibleForRecord(vfrRecord(['id' => 1])))->toBeFalse();
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\SelectColumn;

test('relationship sets relation name and title attribute', function () {
    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name');

    expect($column->getRelationship())->toBe('category')
        ->and($column->getTitleAttribute())->toBe('name');
});

test('relationship name is null by default', function () {
    $column = SelectColumn::make('status');

    expect($column->getRelationship())->toBeNull()
        ->and($column->getTitleAttribute())->toBeNull();
});

test('relationship is chainable with options', function () {
    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name')
        ->options(['1' => 'Tech', '2' => 'Science']);

    expect($column->getRelationship())->toBe('category')
        ->and($column->getOptions())->toBe(['1' => 'Tech', '2' => 'Science']);
});

test('disabled defaults to false', function () {
    $column = SelectColumn::make('status');
    $record = new class extends Model
    {
        protected $guarded = [];
    };

    expect($column->isDisabled($record))->toBeFalse();
});

test('disabled can be set to true', function () {
    $column = SelectColumn::make('status')->disabled(true);
    $record = new class extends Model
    {
        protected $guarded = [];
    };

    expect($column->isDisabled($record))->toBeTrue();
});

test('disabled callback receives record', function () {
    $column = SelectColumn::make('status')
        ->disabled(fn ($record) => $record->locked === true);

    $lockedRecord = new class extends Model
    {
        protected $guarded = [];
    };
    $lockedRecord->locked = true;

    $unlockedRecord = new class extends Model
    {
        protected $guarded = [];
    };
    $unlockedRecord->locked = false;

    expect($column->isDisabled($lockedRecord))->toBeTrue()
        ->and($column->isDisabled($unlockedRecord))->toBeFalse();
});

test('loadRelationshipOptions returns self without relationship', function () {
    $column = SelectColumn::make('status');
    $record = new class extends Model
    {
        protected $guarded = [];
    };

    $result = $column->loadRelationshipOptions($record);

    expect($result)->toBe($column)
        ->and($column->getOptions())->toBe([]);
});

test('loadRelationshipOptions returns self for non-existent method', function () {
    $column = SelectColumn::make('category_id')
        ->relationship('nonExistent', 'name');

    $record = new class extends Model
    {
        protected $guarded = [];
    };

    $result = $column->loadRelationshipOptions($record);

    expect($result)->toBe($column)
        ->and($column->getOptions())->toBe([]);
});

// The cell is the shared combobox, bound to the cell's own write: a trigger per
// row, no teleported list of its own (the list is drawn once per table).
test('the cell renders the shared combobox trigger, not a list of its own', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 3, 'status' => 'active']);

    $column = SelectColumn::make('status')
        ->options(['active' => 'Active', 'archived' => 'Archived']);

    expect($column->renderCell($record))
        ->toContain('wireSearchableSelect')
        ->toContain('wireEditableCell')
        ->not->toContain('<select')
        ->not->toContain('x-teleport')
        ->not->toContain('Archived');
});

test('native() draws the browser select, with the row\'s value selected on first paint', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 3, 'status' => 'archived']);

    $html = SelectColumn::make('status')
        ->options(['active' => 'Active', 'archived' => 'Archived'])
        ->native()
        ->renderCell($record);

    expect($html)
        ->toContain('<select')
        ->toContain('x-model="value"')
        ->toContain('<option value="archived" selected')
        ->not->toContain('<option value="active" selected')
        ->not->toContain('wireSearchableSelect');
});

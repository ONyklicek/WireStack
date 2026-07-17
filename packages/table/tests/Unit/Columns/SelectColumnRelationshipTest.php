<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\SelectColumn;

test('relationship sets relation name and title attribute', function () {
    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name');

    expect($column->getRelationshipName())->toBe('category')
        ->and($column->getTitleAttribute())->toBe('name');
});

test('relationship name is null by default', function () {
    $column = SelectColumn::make('status');

    expect($column->getRelationshipName())->toBeNull()
        ->and($column->getTitleAttribute())->toBeNull();
});

test('relationship is chainable with options', function () {
    $column = SelectColumn::make('category_id')
        ->relationship('category', 'name')
        ->options(['1' => 'Tech', '2' => 'Science']);

    expect($column->getRelationshipName())->toBe('category')
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

// The editable cell commits through wireEditableCell (x-model + commit-on-change),
// which the shared combobox has no binding for — so unlike every other select-like
// surface this one is always a browser-native <select>.
test('the cell renders a native select', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 3, 'status' => 'active']);

    $column = SelectColumn::make('status')
        ->options(['active' => 'Active', 'archived' => 'Archived']);

    expect($column->renderCell($record))
        ->toContain('<select')
        ->toContain('wireEditableCell')
        ->not->toContain('x-teleport');
});

// Because that choice does not exist, the API must not pretend it does: a
// native()/isNative() pair here could only ever be a no-op that reads like a real
// switch. Guards against someone reflexively adding HasNativeControl back.
test('exposes no native() toggle to pretend the choice exists', function () {
    expect(method_exists(SelectColumn::class, 'native'))->toBeFalse()
        ->and(method_exists(SelectColumn::class, 'isNative'))->toBeFalse();
});

<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Select;

test('make creates select with name', function () {
    $field = Select::make('role');

    expect($field->getName())->toBe('role');
});

test('options can be set as array', function () {
    $field = Select::make('role')->options(['admin' => 'Admin', 'user' => 'User']);

    expect($field->getOptions())->toBe(['admin' => 'Admin', 'user' => 'User']);
});

test('options can be closure', function () {
    $field = Select::make('role')->options(fn () => ['a' => 'A']);

    expect($field->getOptions())->toBe(['a' => 'A']);
});

test('searchable flag', function () {
    $field = Select::make('role')->searchable();

    expect($field->isSearchable())->toBeTrue();
});

test('multiple flag', function () {
    $field = Select::make('roles')->multiple();

    expect($field->isMultiple())->toBeTrue();
});

test('native flag', function () {
    $field = Select::make('role')->native();

    expect($field->isNative())->toBeTrue();
});

test('max and min items', function () {
    $field = Select::make('tags')->multiple()->minItems(1)->maxItems(5);

    expect($field->getMinItems())->toBe(1)
        ->and($field->getMaxItems())->toBe(5);
});

test('relationship', function () {
    $field = Select::make('category_id')->relationship('category', 'name');

    expect($field->getRelationship())->toBe('category')
        ->and($field->getTitleAttribute())->toBe('name');
});

test('boolean helper', function () {
    $field = Select::make('active')->boolean();

    $options = $field->getOptions();
    expect($options)->toHaveCount(2);
});

test('allow html flag', function () {
    $field = Select::make('icon')->allowHtml();

    expect($field->isAllowHtml())->toBeTrue();
});

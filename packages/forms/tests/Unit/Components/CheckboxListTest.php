<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\CheckboxList;

test('make creates checkbox list with name', function () {
    expect(CheckboxList::make('roles')->getName())->toBe('roles');
});

test('options can be set and resolved from a closure', function () {
    $field = CheckboxList::make('roles')->options(fn () => ['a' => 'A', 'b' => 'B']);

    expect($field->getOptions())->toBe(['a' => 'A', 'b' => 'B']);
});

test('state type is always array (regression)', function () {
    // A checkbox list holds an array of selected keys; the state definition must
    // reflect that so a stray scalar is normalized rather than left a string.
    expect(CheckboxList::make('roles')->getStateType())->toBe('array');
});

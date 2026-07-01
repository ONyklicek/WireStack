<?php

declare(strict_types=1);

use NyonCode\WireTable\Import\ImportColumn;

test('make sets the name and label defaults to a headline', function () {
    $column = ImportColumn::make('first_name');

    expect($column->getName())->toBe('first_name')
        ->and($column->getLabel())->toBe('First Name');
});

test('label can be overridden with a string or closure', function () {
    expect(ImportColumn::make('name')->label('Full name')->getLabel())->toBe('Full name')
        ->and(ImportColumn::make('name')->label(fn () => 'Lazy')->getLabel())->toBe('Lazy');
});

test('requiredMapping, rules and guesses are configurable', function () {
    $column = ImportColumn::make('email')
        ->requiredMapping()
        ->rules(['required', 'email'])
        ->guess(['e-mail', 'mail']);

    expect($column->isRequiredMapping())->toBeTrue()
        ->and($column->getRules())->toBe(['required', 'email'])
        ->and($column->getGuesses())->toBe(['e-mail', 'mail']);
});

test('defaults: not required, no rules, no guesses', function () {
    $column = ImportColumn::make('name');

    expect($column->isRequiredMapping())->toBeFalse()
        ->and($column->getRules())->toBe([])
        ->and($column->getGuesses())->toBe([]);
});

test('resolveHeader matches by label case-insensitively and trimmed', function () {
    $column = ImportColumn::make('first_name')->label('First Name');

    expect($column->resolveHeader(['  first name  ', 'Email']))->toBe('  first name  ');
});

test('resolveHeader matches by attribute name', function () {
    $column = ImportColumn::make('first_name')->label('Křestní jméno');

    expect($column->resolveHeader(['first_name', 'email']))->toBe('first_name');
});

test('resolveHeader matches by a guess alias', function () {
    $column = ImportColumn::make('email')->guess(['e-mail']);

    expect($column->resolveHeader(['Name', 'E-Mail']))->toBe('E-Mail');
});

test('resolveHeader returns null when no header matches', function () {
    $column = ImportColumn::make('phone');

    expect($column->resolveHeader(['name', 'email']))->toBeNull();
});

test('castState returns the raw value without a cast callback', function () {
    expect(ImportColumn::make('age')->castState('42'))->toBe('42');
});

test('castState applies the cast callback', function () {
    $column = ImportColumn::make('age')->castStateUsing(fn ($value) => (int) $value);

    expect($column->castState('42'))->toBe(42);
});

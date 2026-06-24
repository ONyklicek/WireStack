<?php

declare(strict_types=1);

use Illuminate\Validation\Rules\In;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;

enum SelectTestStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::Published => 'Publikováno',
        };
    }
}

enum SelectTestPriority: string
{
    case LowPriority = 'low';
    case HighPriority = 'high';
}

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

test('options accept an enum class and use HasLabel labels', function () {
    $field = Select::make('status')->options(SelectTestStatus::class);

    expect($field->getOptions())->toBe([
        'draft' => 'Koncept',
        'published' => 'Publikováno',
    ]);
});

test('options accept an enum class without HasLabel, headlining case names', function () {
    $field = Select::make('priority')->options(SelectTestPriority::class);

    expect($field->getOptions())->toBe([
        'low' => 'Low Priority',
        'high' => 'High Priority',
    ]);
});

test('options accept a closure returning an enum class', function () {
    $field = Select::make('status')->options(fn () => SelectTestStatus::class);

    expect($field->getOptions())->toBe([
        'draft' => 'Koncept',
        'published' => 'Publikováno',
    ]);
});

test('enum options add an implicit in: validation rule', function () {
    $field = Select::make('status')->options(SelectTestStatus::class);

    $inRule = collect($field->getValidationRules())
        ->first(fn ($rule) => $rule instanceof In);

    expect($inRule)->not->toBeNull()
        ->and((string) $inRule)->toBe('in:"draft","published"');
});

test('plain array options add no implicit validation rule', function () {
    $field = Select::make('role')->options(['admin' => 'Admin', 'user' => 'User']);

    expect($field->getValidationRules())->toBe([]);
});

test('multiple select does not add an implicit in: rule (array state)', function () {
    $field = Select::make('tags')->options(SelectTestStatus::class)->multiple();

    expect($field->getValidationRules())->toBe([]);
});

test('an explicit in/enum rule is not duplicated by the implicit one', function () {
    $field = Select::make('status')
        ->options(SelectTestStatus::class)
        ->rules(['in:draft']);

    expect($field->getValidationRules())->toBe(['in:draft']);
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

test('state type is string for single and array for multiple (regression)', function () {
    expect(Select::make('status')->getStateType())->toBe('string')
        ->and(Select::make('tags')->multiple()->getStateType())->toBe('array');
});

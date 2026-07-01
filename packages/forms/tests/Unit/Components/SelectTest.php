<?php

declare(strict_types=1);

use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\In;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

function renderSelect(Select $field): string
{
    view()->share('errors', new ViewErrorBag);

    return view('wire-forms::components.select', ['field' => $field])->render();
}

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

test('renders a native select by default (not searchable)', function () {
    $html = renderSelect(Select::make('role')->options(['a' => 'A', 'b' => 'B']));

    expect($html)
        ->toContain('<select')
        ->not->toContain('x-teleport');
});

test('searchable renders the shared combobox instead of a native select (regression: searchable() was a no-op)', function () {
    $html = renderSelect(Select::make('role')->options(['a' => 'A', 'b' => 'B'])->searchable());

    expect($html)
        ->toContain('x-teleport')
        ->toContain('$wire.entangle(')
        ->not->toContain('<select');
});

test('native() forces a native select even when searchable', function () {
    $html = renderSelect(Select::make('role')->options(['a' => 'A'])->searchable()->native());

    expect($html)
        ->toContain('<select')
        ->not->toContain('x-teleport');
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

// ─── Remote search (getSearchResultsUsing / preload / option labels) ──────────

test('preload flag defaults to false and is settable', function () {
    expect(Select::make('user')->isPreloaded())->toBeFalse()
        ->and(Select::make('user')->preload()->isPreloaded())->toBeTrue();
});

test('getSearchResultsUsing implies searchable', function () {
    $field = Select::make('user')->getSearchResultsUsing(fn () => []);

    expect($field->isSearchable())->toBeTrue()
        ->and($field->hasSearchResultsCallback())->toBeTrue();
});

test('a plain searchable select is not remote search', function () {
    $field = Select::make('user')->options(['a' => 'A'])->searchable();

    expect($field->isRemoteSearch())->toBeFalse();
});

test('remote search requires a callback, searchable, and non-native', function () {
    expect(Select::make('user')->getSearchResultsUsing(fn () => [])->isRemoteSearch())->toBeTrue()
        ->and(Select::make('user')->getSearchResultsUsing(fn () => [])->native()->isRemoteSearch())->toBeFalse();
});

test('getSearchResults runs the callback with the search term', function () {
    $field = Select::make('user')->getSearchResultsUsing(
        fn (string $search) => ['u1' => "Match: {$search}"],
    );

    expect($field->getSearchResults('ab'))->toBe(['u1' => 'Match: ab']);
});

test('getSearchResults with no callback returns an empty array', function () {
    expect(Select::make('user')->getSearchResults('x'))->toBe([]);
});

test('getSearchResults coerces a non-array callback result to an empty array', function () {
    $field = Select::make('user')->getSearchResultsUsing(fn () => 'nonsense');

    expect($field->getSearchResults('x'))->toBe([]);
});

test('getSearchResults normalizes an enum class result', function () {
    $field = Select::make('status')->getSearchResultsUsing(fn () => SelectTestStatus::class);

    expect($field->getSearchResults(''))->toBe([
        'draft' => 'Koncept',
        'published' => 'Publikováno',
    ]);
});

test('getPreloadedOptions returns the full list for a client-side select', function () {
    $field = Select::make('role')->options(['a' => 'A', 'b' => 'B'])->searchable();

    expect($field->getPreloadedOptions())->toBe(['a' => 'A', 'b' => 'B']);
});

test('getPreloadedOptions is empty for a non-preloaded remote select', function () {
    $field = Select::make('user')->getSearchResultsUsing(fn () => ['u1' => 'One']);

    expect($field->getPreloadedOptions())->toBe([]);
});

test('getPreloadedOptions eagerly seeds a preloaded remote select', function () {
    $field = Select::make('user')
        ->getSearchResultsUsing(fn (string $search) => $search === '' ? ['u1' => 'One'] : [])
        ->preload();

    expect($field->getPreloadedOptions())->toBe(['u1' => 'One']);
});

test('getOptionLabel falls back to the preloaded option map', function () {
    $field = Select::make('role')->options(['admin' => 'Admin']);

    expect($field->getOptionLabel('admin'))->toBe('Admin')
        ->and($field->getOptionLabel('missing'))->toBeNull()
        ->and($field->getOptionLabel(null))->toBeNull();
});

test('getOptionLabel uses a dedicated callback when set', function () {
    $field = Select::make('user')->getOptionLabelUsing(fn ($value) => "User #{$value}");

    expect($field->getOptionLabel('7'))->toBe('User #7');
});

test('getOptionLabels uses the labels callback when set', function () {
    $field = Select::make('users')->multiple()->getOptionLabelsUsing(
        fn (array $values) => collect($values)->mapWithKeys(fn ($v) => [$v => "User #{$v}"])->all(),
    );

    expect($field->getOptionLabels(['7', '8']))->toBe(['7' => 'User #7', '8' => 'User #8']);
});

test('getOptionLabels falls back to per-value resolution', function () {
    $field = Select::make('roles')->multiple()->options(['admin' => 'Admin', 'user' => 'User']);

    expect($field->getOptionLabels(['admin', 'user']))->toBe(['admin' => 'Admin', 'user' => 'User']);
});

test('getSelectedOptionLabels resolves a single scalar value', function () {
    $field = Select::make('user')->getOptionLabelUsing(fn ($value) => "User #{$value}");

    expect($field->getSelectedOptionLabels('7'))->toBe(['7' => 'User #7'])
        ->and($field->getSelectedOptionLabels(null))->toBe([]);
});

test('getSelectedOptionLabels ignores an array value on a single select', function () {
    $field = Select::make('user')->getOptionLabelUsing(fn ($value) => "User #{$value}");

    expect($field->getSelectedOptionLabels(['7', '8']))->toBe([]);
});

test('getSelectedOptionLabels resolves a multiple value array', function () {
    $field = Select::make('users')->multiple()->getOptionLabelsUsing(
        fn (array $values) => collect($values)->mapWithKeys(fn ($v) => [$v => "User #{$v}"])->all(),
    );

    expect($field->getSelectedOptionLabels(['7', '8']))->toBe(['7' => 'User #7', '8' => 'User #8'])
        ->and($field->getSelectedOptionLabels([]))->toBe([]);
});

test('remote searchable select renders the async combobox wiring', function () {
    $html = renderSelect(
        Select::make('user')->getSearchResultsUsing(fn () => ['u1' => 'One']),
    );

    expect($html)
        ->toContain('remote: true')
        ->toContain("searchSelectOptions('user'");
});

test('a client-side searchable select is not flagged for remote search', function () {
    $html = renderSelect(Select::make('role')->options(['a' => 'A'])->searchable());

    expect($html)->toContain('remote: false');
});

// ─── Create option modal (createOptionForm / createOptionUsing) ───────────────

test('createOptionForm flags the field and implies searchable', function () {
    $field = Select::make('category')->createOptionForm([TextInput::make('name')]);

    expect($field->hasCreateOptionForm())->toBeTrue()
        ->and($field->isSearchable())->toBeTrue();
});

test('a select has no create option form by default', function () {
    expect(Select::make('category')->hasCreateOptionForm())->toBeFalse();
});

test('getCreateOptionForm builds a form bound to the create-option state path', function () {
    $field = Select::make('category')->createOptionForm([TextInput::make('name')]);

    $form = $field->getCreateOptionForm();

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->getFlatComponents())->toHaveCount(1);
});

test('getCreateOptionForm accepts a closure schema', function () {
    $field = Select::make('category')->createOptionForm(fn () => [TextInput::make('name')]);

    expect($field->getCreateOptionForm())->toBeInstanceOf(Form::class);
});

test('getCreateOptionForm returns null without a schema', function () {
    expect(Select::make('category')->getCreateOptionForm())->toBeNull();
});

test('getCreateOptionForm returns null when the closure schema is not an array', function () {
    $field = Select::make('category')->createOptionForm(fn () => 'nonsense');

    expect($field->getCreateOptionForm())->toBeNull();
});

test('createOption returns null without a callback', function () {
    $field = Select::make('category')->createOptionForm([TextInput::make('name')]);

    expect($field->createOption(['name' => 'Books']))->toBeNull();
});

test('createOption returns a scalar value from the callback', function () {
    $field = Select::make('category')->createOptionUsing(fn (array $data) => 'cat-'.$data['name']);

    expect($field->createOption(['name' => 'books']))->toBe('cat-books');
});

test('createOption returns the raw callback result for the host to normalize', function () {
    $model = new class
    {
        public function getKey(): int
        {
            return 42;
        }
    };

    $field = Select::make('category')->createOptionUsing(fn () => $model);

    expect($field->createOption([]))->toBe($model);
});

test('normalizeOptionValue extracts the key from a model-like object', function () {
    $result = Select::normalizeOptionValue(new class
    {
        public function getKey(): int
        {
            return 42;
        }
    });

    expect($result)->toBe(42);
});

test('normalizeOptionValue passes scalar keys through', function () {
    expect(Select::normalizeOptionValue('cat-1'))->toBe('cat-1')
        ->and(Select::normalizeOptionValue(7))->toBe(7);
});

test('normalizeOptionValue returns null for a non-scalar, non-model result', function () {
    expect(Select::normalizeOptionValue(['not', 'a', 'key']))->toBeNull()
        ->and(Select::normalizeOptionValue(null))->toBeNull();
});

test('create option modal heading defaults and is overridable', function () {
    expect(Select::make('category')->getCreateOptionModalHeading())->toBe('Create option')
        ->and(Select::make('category')->createOptionModalHeading('New tag')->getCreateOptionModalHeading())->toBe('New tag');
});

// ─── Edit option modal (editOptionForm / fillEditOptionUsing / updateOptionUsing) ─

test('editOptionForm flags the field and implies searchable', function () {
    $field = Select::make('category')->editOptionForm([TextInput::make('name')]);

    expect($field->hasEditOptionForm())->toBeTrue()
        ->and($field->isSearchable())->toBeTrue();
});

test('a select has no edit option form by default', function () {
    expect(Select::make('category')->hasEditOptionForm())->toBeFalse();
});

test('getEditOptionForm builds a form bound to the edit-option state path', function () {
    $field = Select::make('category')->editOptionForm([TextInput::make('name')]);

    $form = $field->getEditOptionForm();

    expect($form)->toBeInstanceOf(Form::class)
        ->and($form->getFlatComponents())->toHaveCount(1);
});

test('getEditOptionForm accepts a closure schema', function () {
    $field = Select::make('category')->editOptionForm(fn () => [TextInput::make('name')]);

    expect($field->getEditOptionForm())->toBeInstanceOf(Form::class);
});

test('getEditOptionForm returns null without a schema', function () {
    expect(Select::make('category')->getEditOptionForm())->toBeNull();
});

test('getEditOptionForm returns null when the closure schema is not an array', function () {
    $field = Select::make('category')->editOptionForm(fn () => 'nonsense');

    expect($field->getEditOptionForm())->toBeNull();
});

test('getEditOptionFormData returns an empty array without a fill callback', function () {
    $field = Select::make('category')->editOptionForm([TextInput::make('name')]);

    expect($field->getEditOptionFormData('c1'))->toBe([]);
});

test('getEditOptionFormData runs the fill callback with the selected value', function () {
    $field = Select::make('category')->fillEditOptionUsing(fn ($value) => ['name' => "Record {$value}"]);

    expect($field->getEditOptionFormData('c1'))->toBe(['name' => 'Record c1']);
});

test('getEditOptionFormData coerces a non-array fill result to an empty array', function () {
    $field = Select::make('category')->fillEditOptionUsing(fn () => 'nope');

    expect($field->getEditOptionFormData('c1'))->toBe([]);
});

test('updateOption runs the update callback with the value and data', function () {
    $captured = null;
    $field = Select::make('category')->updateOptionUsing(function ($value, array $data) use (&$captured) {
        $captured = [$value, $data];
    });

    $field->updateOption('c1', ['name' => 'Renamed']);

    expect($captured)->toBe(['c1', ['name' => 'Renamed']]);
});

test('updateOption is a no-op without an update callback', function () {
    $field = Select::make('category')->editOptionForm([TextInput::make('name')]);

    expect(fn () => $field->updateOption('c1', ['name' => 'X']))->not->toThrow(Exception::class);
});

test('edit option modal heading defaults and is overridable', function () {
    expect(Select::make('category')->getEditOptionModalHeading())->toBe('Edit option')
        ->and(Select::make('category')->editOptionModalHeading('Change')->getEditOptionModalHeading())->toBe('Change');
});

<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireTable\Columns\TextInputColumn;
use Workbench\App\Models\User;

function ticRecord(array $attributes = []): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };

    $record->forceFill($attributes + ['id' => 1]);

    return $record;
}

// ─── Construction & input types ─────────────────────────────────

it('is editable and text type by default', function () {
    $column = TextInputColumn::make('name');

    expect($column)->toBeInstanceOf(TextInputColumn::class)
        ->and($column->isEditable())->toBeTrue()
        ->and($column->getInputType())->toBe('text');
});

it('exposes input type variants', function () {
    expect(TextInputColumn::make('a')->type('search')->getInputType())->toBe('search')
        ->and(TextInputColumn::make('a')->numeric()->getInputType())->toBe('number')
        ->and(TextInputColumn::make('a')->email()->getInputType())->toBe('email')
        ->and(TextInputColumn::make('a')->tel()->getInputType())->toBe('tel')
        ->and(TextInputColumn::make('a')->url()->getInputType())->toBe('url')
        ->and(TextInputColumn::make('a')->password()->getInputType())->toBe('password');
});

it('integer and decimal set a numeric step', function () {
    expect(TextInputColumn::make('a')->integer()->buildInputAttributes())->toContain('step="1"')
        ->and(TextInputColumn::make('a')->decimal()->buildInputAttributes())->toContain('step="0.01"')
        ->and(TextInputColumn::make('a')->decimal(1)->buildInputAttributes())->toContain('step="0.1"')
        ->and(TextInputColumn::make('a')->decimal(3)->buildInputAttributes())->toContain('step="0.001"');
});

it('money and czk configure decimal formatting', function () {
    $config = TextInputColumn::make('price')->money(2, '.', ',')->getConfig(ticRecord());

    expect($config['type'])->toBe('text')
        ->and($config['decimals'])->toBe(2)
        ->and($config['thousandsSeparator'])->toBe('.')
        ->and($config['decimalSeparator'])->toBe(',');

    $czk = TextInputColumn::make('price')->czk(0)->getConfig(ticRecord());
    expect($czk['decimals'])->toBe(0);
});

// ─── Input attributes & classes ─────────────────────────────────

it('builds input attributes from constraints', function () {
    $attrs = TextInputColumn::make('name')
        ->placeholder('Enter name')
        ->maxLength(50)
        ->minLength(3)
        ->pattern('[A-Z]+')
        ->step('5')
        ->min('10')
        ->max('100')
        ->autocomplete('name')
        ->autofocus()
        ->buildInputAttributes();

    expect($attrs)
        ->toContain('type="text"')
        ->toContain('placeholder="Enter name"')
        ->toContain('maxlength="50"')
        ->toContain('minlength="3"')
        ->toContain('pattern="[A-Z]+"')
        ->toContain('step="5"')
        ->toContain('min="10"')
        ->toContain('max="100"')
        ->toContain('autocomplete="name"')
        ->toContain('autofocus="autofocus"');
});

it('renders min/max/step of "0" (not dropped as falsy)', function () {
    $attrs = TextInputColumn::make('qty')->min('0')->max('0')->step('0')->buildInputAttributes();

    expect($attrs)
        ->toContain('min="0"')
        ->toContain('max="0"')
        ->toContain('step="0"');
});

it('builds input classes with prefix, suffix and custom class', function () {
    $column = TextInputColumn::make('name')->inputClass('custom-cls');

    expect($column->buildInputClasses(false, false))->not->toContain('pl-7')
        ->and($column->buildInputClasses(true, true))
        ->toContain('pl-7')->toContain('pr-8')->toContain('custom-cls');
});

// ─── Validation ─────────────────────────────────────────────────

it('returns valid when there are no rules', function () {
    expect(TextInputColumn::make('name')->validate('x', ticRecord()))
        ->toBe(['valid' => true, 'errors' => []]);
});

it('validates against array rules', function () {
    $column = TextInputColumn::make('age')->rules(['integer', 'min:18']);

    expect($column->validate(25, ticRecord())['valid'])->toBeTrue();

    $fail = $column->validate(10, ticRecord());
    expect($fail['valid'])->toBeFalse()
        ->and($fail['errors'])->not->toBeEmpty();
});

it('required() adds a required rule and rule() appends', function () {
    $column = TextInputColumn::make('name')->required()->rule('string');

    expect($column->getRules(ticRecord()))->toBe(['required', 'string']);

    // required(false) is a no-op
    expect(TextInputColumn::make('x')->required(false)->getRules(ticRecord()))->toBe([]);
});

it('supports closure rules', function () {
    $column = TextInputColumn::make('name')->rules(fn ($record) => ['required']);

    expect($column->getRules(ticRecord()))->toBe(['required']);
});

it('uses custom messages and falls back to label as attribute', function () {
    $column = TextInputColumn::make('email')
        ->label('E-mail address')
        ->rules(['required'])
        ->validationMessages(['email.required' => 'Need it!']);

    $result = $column->validate('', ticRecord());

    expect($result['valid'])->toBeFalse()
        ->and($result['errors'][0])->toBe('Need it!');

    // Explicit validation attribute is honoured too.
    $explicit = TextInputColumn::make('email')
        ->validationAttribute('Email')
        ->rules(['required'])
        ->validate('', ticRecord());
    expect($explicit['valid'])->toBeFalse();
});

// ─── Disabled / readonly state ──────────────────────────────────

it('handles disabled as bool and closure', function () {
    expect(TextInputColumn::make('a')->isDisabled(ticRecord()))->toBeFalse()
        ->and(TextInputColumn::make('a')->disabled()->isDisabled(ticRecord()))->toBeTrue()
        ->and(TextInputColumn::make('a')->disabled(fn ($r) => $r->locked)->isDisabled(ticRecord(['locked' => true])))->toBeTrue();
});

it('handles readonly as bool and closure', function () {
    expect(TextInputColumn::make('a')->isReadonly(ticRecord()))->toBeFalse()
        ->and(TextInputColumn::make('a')->readonly()->isReadonly(ticRecord()))->toBeTrue()
        ->and(TextInputColumn::make('a')->readonly(fn ($r) => $r->locked)->isReadonly(ticRecord(['locked' => true])))->toBeTrue();
});

// ─── canEdit / permissions ──────────────────────────────────────

it('cannot edit when disabled or readonly', function () {
    expect(TextInputColumn::make('a')->disabled()->canEdit(ticRecord()))->toBeFalse()
        ->and(TextInputColumn::make('a')->readonly()->canEdit(ticRecord()))->toBeFalse();
});

it('can edit by default and respects edit permission with no user', function () {
    expect(TextInputColumn::make('a')->canEdit(ticRecord()))->toBeTrue()
        ->and(TextInputColumn::make('a')->editPermission('edit-tasks')->canEdit(ticRecord()))->toBeFalse();
});

it('grants edit to Super Admin role', function () {
    $user = new class extends User
    {
        public function hasRole(string $role): bool
        {
            return $role === 'Super Admin';
        }
    };
    $this->actingAs($user);

    expect(TextInputColumn::make('a')->editPermission('edit-tasks')->canEdit(ticRecord()))->toBeTrue();
});

it('delegates to hasPermissionTo when present', function () {
    $user = new class extends User
    {
        public function hasRole(string $role): bool
        {
            return false;
        }

        public function hasPermissionTo(string $permission): bool
        {
            return $permission === 'edit-tasks';
        }
    };
    $this->actingAs($user);

    expect(TextInputColumn::make('a')->editPermission('edit-tasks')->canEdit(ticRecord()))->toBeTrue()
        ->and(TextInputColumn::make('a')->editPermission('other')->canEdit(ticRecord()))->toBeFalse();
});

it('falls back to the can() gate check', function () {
    Gate::define('edit-tasks', fn ($user) => true);
    $this->actingAs(new User);

    expect(TextInputColumn::make('a')->editPermission('edit-tasks')->canEdit(ticRecord()))->toBeTrue();
});

it('denies when the user has no authorization methods', function () {
    $user = new GenericUser(['id' => 1]);
    $this->actingAs($user);

    expect(TextInputColumn::make('a')->editPermission('edit-tasks')->canEdit(ticRecord()))->toBeFalse();
});

// ─── Save / load formatting ─────────────────────────────────────

it('trims and nullifies empty values on save', function () {
    $column = TextInputColumn::make('name')->nullable();

    expect($column->formatForSave('  hi  ', ticRecord()))->toBe('hi')
        ->and($column->formatForSave('   ', ticRecord()))->toBeNull();

    expect(TextInputColumn::make('name')->trim(false)->formatForSave(' keep ', ticRecord()))->toBe(' keep ');
});

it('parses formatted numbers back to floats on save', function () {
    $column = TextInputColumn::make('price')->money(2, ' ', ',');

    expect($column->formatForSave('1 234,50', ticRecord()))->toBe(1234.5)
        ->and($column->formatForSave('', ticRecord()))->toBe('');
});

it('applies case transforms and before-save formatter', function () {
    expect(TextInputColumn::make('a')->uppercase()->formatForSave('abc', ticRecord()))->toBe('ABC')
        ->and(TextInputColumn::make('a')->lowercase()->formatForSave('ABC', ticRecord()))->toBe('abc')
        ->and(TextInputColumn::make('a')->beforeSave(fn ($v) => $v.'!')->formatForSave('x', ticRecord()))->toBe('x!');
});

it('formats numbers and runs the after-load formatter on load', function () {
    $column = TextInputColumn::make('price')->money(2, ' ', ',');
    expect($column->formatAfterLoad(1234.5, ticRecord()))->toBe('1 234,50');

    $custom = TextInputColumn::make('name')->afterLoad(fn ($v) => strtoupper((string) $v));
    expect($custom->formatAfterLoad('hi', ticRecord()))->toBe('HI');
});

it('formats values for readonly display', function () {
    expect(TextInputColumn::make('a')->displayFormat(fn ($v) => "#$v")->formatForDisplay('x', ticRecord()))->toBe('#x')
        ->and(TextInputColumn::make('p')->money(2)->formatForDisplay(99.9, ticRecord()))->toBe('99,90')
        ->and(TextInputColumn::make('a')->formatForDisplay('plain', ticRecord()))->toBe('plain')
        ->and(TextInputColumn::make('a')->formatForDisplay(null, ticRecord()))->toBe('');
});

// ─── Callbacks getters ──────────────────────────────────────────

it('stores save callbacks and formatters', function () {
    $after = fn () => null;
    $save = fn () => null;
    $before = fn () => null;

    $column = TextInputColumn::make('a')
        ->afterStateUpdated($after)
        ->saveUsing($save)
        ->beforeSave($before);

    expect($column->getAfterStateUpdatedCallback())->toBe($after)
        ->and($column->getSaveCallback())->toBe($save)
        ->and($column->getBeforeSaveFormatter())->toBe($before);
});

// ─── Blade getters & save behaviour flags ───────────────────────

it('exposes blade getters and save behaviour flags', function () {
    $column = TextInputColumn::make('a')
        ->inputPrefix('$')
        ->inputSuffix('kg')
        ->helperText('hint')
        ->saveOnBlur(false)
        ->saveOnEnter(false)
        ->liveValidation(true, 250);

    expect($column->getInputPrefix())->toBe('$')
        ->and($column->getInputSuffix())->toBe('kg')
        ->and($column->getHelperText())->toBe('hint')
        ->and($column->getSaveOnBlur())->toBeFalse()
        ->and($column->getSaveOnEnter())->toBeFalse()
        ->and($column->getLiveValidation())->toBeTrue()
        ->and($column->getLiveDebounce())->toBe(250);
});

// ─── Config export ──────────────────────────────────────────────

it('exports a full config array', function () {
    $config = TextInputColumn::make('name')
        ->maxLength(10)
        ->minLength(2)
        ->pattern('x')
        ->required()
        ->getConfig(ticRecord());

    expect($config)->toMatchArray([
        'name' => 'name',
        'type' => 'text',
        'canEdit' => true,
        'isDisabled' => false,
        'isReadonly' => false,
        'maxLength' => 10,
        'minLength' => 2,
        'pattern' => 'x',
        'hasRules' => true,
    ]);
});

// ─── Cell rendering ─────────────────────────────────────────────

it('renders an editable cell when editing is allowed', function () {
    $html = TextInputColumn::make('name')->renderCell(ticRecord(['name' => 'Ada']));

    expect($html)->toContain('Ada');
});

it('renders a readonly cell when editing is not allowed', function () {
    $html = TextInputColumn::make('name')->disabled()->renderCell(ticRecord(['name' => 'Ada']));

    expect($html)->toContain('Ada');
});

it('renders nothing when the column is not viewable', function () {
    Gate::define('view-secret', fn () => false);

    $html = TextInputColumn::make('name')->authorize('view-secret')->renderCell(ticRecord(['name' => 'Ada']));

    expect($html)->toBe('');
});

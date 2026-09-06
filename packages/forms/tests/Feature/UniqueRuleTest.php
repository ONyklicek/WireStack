<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * unique(): the table from the model, the column from the field, and the record
 * being edited excluded from its own check.
 *
 * The hand-written string this replaces — `unique:companies,name,{id}` — is
 * wrong in one mode whichever way it is written: without the id an edit fails
 * against itself, and with one there is nothing to append in create mode.
 */

class UrCompany extends Model
{
    protected $table = 'ur_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class UrHost extends Component
{
    use WithForms;

    public ?int $companyId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model($this->companyId !== null ? UrCompany::find($this->companyId) : UrCompany::class)
            ->statePath('data')
            ->schema([
                TextInput::make('name')->unique(),
                TextInput::make('vat_number')->unique(),
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('ur_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('vat_number')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('ur_companies');
});

it('rejects a value already taken when creating', function () {
    UrCompany::create(['name' => 'Acme']);

    Livewire::test(UrHost::class)
        ->set('data.name', 'Acme')
        ->call('save')
        ->assertHasErrors(['data.name']);

    expect(UrCompany::count())->toBe(1);
});

it('accepts a free value when creating', function () {
    UrCompany::create(['name' => 'Acme']);

    Livewire::test(UrHost::class)
        ->set('data.name', 'Globex')
        ->call('save')
        ->assertHasNoErrors();

    expect(UrCompany::count())->toBe(2);
});

it('does not fail an edited record against itself', function () {
    $company = UrCompany::create(['name' => 'Acme']);

    Livewire::test(UrHost::class, ['companyId' => $company->id])
        ->set('data.name', 'Acme')
        ->set('data.vat_number', 'CZ123')
        ->call('save')
        ->assertHasNoErrors();

    expect($company->fresh()->vat_number)->toBe('CZ123');
});

it('still catches a collision with another record when editing', function () {
    UrCompany::create(['name' => 'Acme']);
    $globex = UrCompany::create(['name' => 'Globex']);

    Livewire::test(UrHost::class, ['companyId' => $globex->id])
        ->set('data.name', 'Acme')
        ->call('save')
        ->assertHasErrors(['data.name']);
});

it('checks the table and column it was given rather than the ones it would infer', function () {
    $rules = TextInput::make('tax_id')
        ->record(new UrCompany)
        ->unique(table: 'ur_companies', column: 'vat_number')
        ->getValidationRules();

    expect((string) $rules[0])->toBe('unique:ur_companies,vat_number,NULL,id');
});

it('rejects a value already taken in a second unique field', function () {
    UrCompany::create(['name' => 'Acme', 'vat_number' => 'CZ123']);

    Livewire::test(UrHost::class)
        ->set('data.name', 'Globex')
        ->set('data.vat_number', 'CZ123')
        ->call('save')
        ->assertHasErrors(['data.vat_number']);
});

it('scopes the rule further through modifyRuleUsing()', function () {
    $rules = TextInput::make('name')
        ->unique(table: 'ur_companies', modifyRuleUsing: fn ($rule) => $rule->where('name', 'Acme'))
        ->getValidationRules();

    expect((string) $rules[0])->toContain('ur_companies')
        ->and((string) $rules[0])->toContain('Acme');
});

it('keeps the record in the check when ignoreRecord is off', function () {
    $company = UrCompany::create(['name' => 'Acme']);

    $ignoring = TextInput::make('name')->record($company)->unique()->getValidationRules();
    $checking = TextInput::make('name')->record($company)->unique(ignoreRecord: false)->getValidationRules();

    expect((string) $ignoring[0])->toBe('unique:ur_companies,name,"'.$company->id.'",id')
        ->and((string) $checking[0])->toBe('unique:ur_companies,name,NULL,id');
});

it('says what to pass when there is no model to take a table from', function () {
    expect(fn () => TextInput::make('name')->unique()->getValidationRules())
        ->toThrow(FormConfigurationException::class, 'has no model to take a table from');
});

it('has no record to ignore on a component that is not bound to one', function () {
    expect(fn () => Repeater::make('rows')->unique()->getValidationRules())
        ->toThrow(FormConfigurationException::class);
});

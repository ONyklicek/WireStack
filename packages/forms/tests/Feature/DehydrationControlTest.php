<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * dehydrated() / dehydrateStateUsing(): what reaches the record, and as what.
 *
 * Regression: a password confirmation is a field with a rule and no column. The
 * dehydrator sets every key it is given as an attribute, so the documented
 * confirmation example wrote `password_confirmation` onto the model and the save
 * fataled on "no such column". Owners now say so, and the save handler asks the
 * schema instead of carrying a branch per known case.
 */

class DcUser extends Model
{
    protected $table = 'dc_users';

    protected $guarded = [];

    public $timestamps = false;

    /** @var array<string, string> */
    protected $casts = ['logins' => 'array'];
}

class DcHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DcUser::class)
            ->statePath('data')
            ->schema([
                TextInput::make('email'),
                TextInput::make('password')
                    ->rules(['confirmed'])
                    ->dehydrateStateUsing(static fn (mixed $state): string => 'hashed:'.$state),
                TextInput::make('password_confirmation')->dehydrated(false),
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

class DcConditionalHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DcUser::class)
            ->statePath('data')
            ->schema([
                TextInput::make('email'),
                // Written only while the sibling toggle says so — the closure is
                // resolved against live state at save time, not at declaration.
                TextInput::make('nickname')->dehydrated(fn (callable $get): bool => (bool) $get('store_nickname')),
                TextInput::make('store_nickname')->dehydrated(false),
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

class DcRepeaterHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DcUser::class)
            ->statePath('data')
            ->schema([
                TextInput::make('email'),
                Repeater::make('logins')->schema([
                    TextInput::make('label')
                        ->dehydrateStateUsing(static fn (mixed $state): string => mb_strtoupper((string) $state)),
                    DateTimePicker::make('at')->format('Y-m-d'),
                ]),
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
    Schema::create('dc_users', function (Blueprint $t) {
        $t->id();
        $t->string('email');
        $t->string('password')->nullable();
        $t->string('nickname')->nullable();
        $t->json('logins')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('dc_users');
});

it('keeps a dehydrated(false) field out of the record', function () {
    Livewire::test(DcHost::class)
        ->set('data.email', 'ada@example.com')
        ->set('data.password', 'secret')
        ->set('data.password_confirmation', 'secret')
        ->call('save')
        ->assertHasNoErrors();

    $user = DcUser::first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('ada@example.com')
        ->and($user->getAttributes())->not->toHaveKey('password_confirmation');
});

it('writes the value dehydrateStateUsing() returns', function () {
    Livewire::test(DcHost::class)
        ->set('data.email', 'ada@example.com')
        ->set('data.password', 'secret')
        ->set('data.password_confirmation', 'secret')
        ->call('save');

    expect(DcUser::first()->password)->toBe('hashed:secret');
});

it('resolves a dehydrated() closure against live sibling state', function () {
    Livewire::test(DcConditionalHost::class)
        ->set('data.email', 'ada@example.com')
        ->set('data.nickname', 'Ada')
        ->set('data.store_nickname', false)
        ->call('save');

    expect(DcUser::first()->nickname)->toBeNull();

    DcUser::query()->delete();

    Livewire::test(DcConditionalHost::class)
        ->set('data.email', 'ada@example.com')
        ->set('data.nickname', 'Ada')
        ->set('data.store_nickname', true)
        ->call('save');

    expect(DcUser::first()->nickname)->toBe('Ada');
});

it('applies a repeater child dehydrateStateUsing() after the child field own transform', function () {
    Livewire::test(DcRepeaterHost::class)
        ->set('data.email', 'ada@example.com')
        ->set('data.logins', [['label' => 'laptop', 'at' => '2026-03-04 15:30']])
        ->call('save');

    $logins = DcUser::first()->logins;

    expect($logins[0]['label'])->toBe('LAPTOP')
        // The picker's own storeFormat ran; the owner callback did not replace it.
        ->and($logins[0]['at'])->toBe('2026-03-04');
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A halt form is parked between requests, and until 2.0 every host wrote it to
 * one shared key (`wire.halt_form_instance`, in the session) — so a second table,
 * or the same page in a second tab, restored someone else's schema into its own
 * halt modal. It is now keyed by the Livewire component id.
 */

class HfiUser extends Model
{
    protected $table = 'hfi_users';

    protected $guarded = [];

    public $timestamps = false;
}

abstract class HfiComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(HfiUser::class)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('ask')->action(fn ($halt) => $halt()
                    ->heading('Why?')
                    ->form([TextInput::make(static::field())])),
            ]);
    }

    abstract public static function field(): string;

    public function render(): string
    {
        return '<div></div>';
    }
}

class HfiFirst extends HfiComponent
{
    public static function field(): string
    {
        return 'reason';
    }
}

class HfiSecond extends HfiComponent
{
    public static function field(): string
    {
        return 'justification';
    }
}

beforeEach(function () {
    Schema::create('hfi_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    HfiUser::create(['id' => 1, 'name' => 'Ada']);
});

afterEach(function () {
    Schema::dropIfExists('hfi_users');
});

it('keeps each host\'s halt form to itself', function () {
    $first = Livewire::test(HfiFirst::class)->call('executeTableActionWithData', '1', 'ask');
    $second = Livewire::test(HfiSecond::class)->call('executeTableActionWithData', '1', 'ask');

    // Both are open at once, which is the case the shared key could not survive.
    expect($first->get('tableState.modal.halt.show'))->toBeTrue()
        ->and($second->get('tableState.modal.halt.show'))->toBeTrue();

    // The next request is where it mattered: the instance is rebuilt from the
    // session, so with one key the first host came back holding the second's
    // schema — a modal asking for a field its own action never declared.
    $first->call('$refresh');
    $second->call('$refresh');

    $fieldsOf = fn ($component) => collect($component->instance()->getHaltModalFormInstance()?->getSchema() ?? [])
        ->map(fn ($field) => $field->getName())->all();

    expect($fieldsOf($first))->toBe(['reason'])
        ->and($fieldsOf($second))->toBe(['justification']);
});

it('parks the schema under a key of its own, in the cache', function () {
    // The cache, not the session: on the `cookie` session driver a serialized
    // schema does not fit in the 4 KB a cookie holds, so it was written and
    // silently gone by the next request — the modal came back with no fields.
    $component = Livewire::test(HfiFirst::class)->call('executeTableActionWithData', '1', 'ask');

    expect(Cache::has('wire.halt_form.'.$component->id()))->toBeTrue()
        ->and(session()->has('wire.halt_form_instance'))->toBeFalse();

    $component->call('closeHaltModal');

    expect(Cache::has('wire.halt_form.'.$component->id()))->toBeFalse();
});

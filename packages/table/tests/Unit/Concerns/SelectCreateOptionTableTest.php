<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Select::createOptionForm() inside a table action modal (regression: the
 * "+ Create" button rendered but WithTable lacked InteractsWithSelectCreation,
 * so mountCreateOption() was a missing method — audit matrix A, TM column).
 */
class ScotUser extends Model
{
    protected $table = 'scot_users';

    protected $guarded = [];

    public $timestamps = false;
}

class ScotComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(ScotUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->form([
                        Select::make('category')
                            ->options(['old' => 'Old'])
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                            ])
                            ->createOptionUsing(fn (array $data) => 'created-'.$data['name']),
                        Select::make('tags')
                            ->multiple()
                            ->options(['a' => 'A'])
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                            ])
                            ->createOptionUsing(fn (array $data) => $data['name']),
                    ]),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('scot_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    Schema::dropIfExists('scot_users');
});

it('creates and selects a new option from inside a table action modal (StateContainer bag write)', function () {
    $test = Livewire::test(ScotComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('mountCreateOption', 'tableState.modal.action.formData.category')
        ->assertSet('mountedCreateOptionSelect', 'tableState.modal.action.formData.category')
        ->set('createOptionFormData.name', 'Fresh')
        ->call('createSelectOption')
        ->assertSet('mountedCreateOptionSelect', null);

    // The write goes through StateContainer::writeInto — a plain data_set would
    // silently drop it on the tableState bag.
    expect($test->instance()->tableState->get('modal.action.formData.category'))->toBe('created-Fresh');
});

it('appends the created option for a multi-select inside a table action modal', function () {
    $test = Livewire::test(ScotComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData', ['tags' => ['a']])
        ->call('mountCreateOption', 'tableState.modal.action.formData.tags')
        ->set('createOptionFormData.name', 'b')
        ->call('createSelectOption');

    expect($test->instance()->tableState->get('modal.action.formData.tags'))->toBe(['a', 'b']);
});

it('keeps the create-option modal open with errors when the option form is invalid', function () {
    Livewire::test(ScotComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('mountCreateOption', 'tableState.modal.action.formData.category')
        ->set('createOptionFormData.name', '')
        ->call('createSelectOption')
        ->assertHasErrors()
        ->assertSet('mountedCreateOptionSelect', 'tableState.modal.action.formData.category');
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class FatUser extends Model
{
    protected $table = 'fat_users';

    protected $guarded = [];

    public $timestamps = false;
}

class FatComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FatUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->form([
                        TextInput::make('title')->suffixAction(
                            Action::make('to_upper')
                                ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
                        ),
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

    Schema::create('fat_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    Schema::dropIfExists('fat_users');
});

it('runs a field affix action inside an action modal form', function () {
    $test = Livewire::test(FatComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData', ['title' => 'hello'])
        ->call('callFieldAction', 'tableState.modal.action.formData.title', 'to_upper');

    expect($test->instance()->tableState->get('modal.action.formData.title'))->toBe('HELLO');
});

it('ignores a field action when no modal form is open', function () {
    $test = Livewire::test(FatComponent::class)
        ->call('callFieldAction', 'tableState.modal.action.formData.title', 'to_upper');

    expect($test->instance()->tableState->get('modal.action.formData.title'))->toBeNull();
});

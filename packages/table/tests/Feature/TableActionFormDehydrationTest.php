<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The table's own door to an action callback: submitActionModal().
 *
 * The dehydration seam is declared in wire-core and implemented in wire-forms,
 * but the table has its own submit path — a row, header and bulk action all
 * arrive through it — so it gets its own coverage. wire-forms'
 * ActionFormDehydrationTest covers the standalone host.
 */

enum TafdStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }
}

class TafdUser extends Model
{
    protected $table = 'tafd_users';

    protected $guarded = [];

    public $timestamps = false;
}

class TafdComponent extends Component
{
    use WithTable;

    /** @var array<string, mixed> What the last action callback was handed. */
    public array $seen = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(TafdUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('invite')
                    ->form([
                        Select::make('status')->options(TafdStatus::class)->placeholder('None'),
                        TextInput::make('quota')->integer(),
                    ])
                    ->action(fn (array $data) => $this->seen = array_merge(['ran' => true], $data)),
            ])
            ->actions([
                Action::make('edit')
                    ->form([Select::make('status')->options(TafdStatus::class)->placeholder('None')])
                    ->action(fn (array $data) => $this->seen = array_merge(['ran' => true], $data)),

                // The third door: the action asks for a form mid-flight and is
                // re-executed with what the halt modal collected.
                Action::make('archive')
                    ->action(function (bool $confirmed, array $data, callable $halt) {
                        if (! $confirmed) {
                            return $halt()
                                ->heading('Why?')
                                ->form([
                                    Select::make('status')->options(TafdStatus::class)->placeholder('None'),
                                    TextInput::make('quota')->integer(),
                                ]);
                        }

                        $this->seen = array_merge(['ran' => true], $data);
                    }),

                // A halt whose form carries a field rule of its own.
                Action::make('close')
                    ->action(function (bool $confirmed, array $data, callable $halt) {
                        if (! $confirmed) {
                            return $halt()
                                ->heading('Why?')
                                ->form([TextInput::make('reason')->required()]);
                        }

                        $this->seen = array_merge(['ran' => true], $data);
                    }),

                // A halt declaring extra rules over the bag as a whole.
                Action::make('escalate')
                    ->action(function (bool $confirmed, array $data, callable $halt) {
                        if (! $confirmed) {
                            return $halt()
                                ->heading('To whom?')
                                ->form([TextInput::make('assignee')])
                                ->validation(
                                    ['assignee' => 'required|min:3'],
                                    ['assignee.min' => 'Too short.'],
                                );
                        }

                        $this->seen = array_merge(['ran' => true], $data);
                    }),

                // A plain confirmation: no form, so nothing to shape.
                Action::make('purge')
                    ->action(function (bool $confirmed, array $data, callable $halt) {
                        if (! $confirmed) {
                            return $halt()->heading('Sure?');
                        }

                        $this->seen = array_merge(['ran' => true], $data);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulkEdit')
                    ->form([Select::make('status')->options(TafdStatus::class)->placeholder('None')])
                    ->action(fn (array $data) => $this->seen = array_merge(['ran' => true], $data)),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}

beforeEach(function () {
    Schema::dropIfExists('tafd_users');
    Schema::create('tafd_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    TafdUser::query()->create(['name' => 'Ada']);
});

it('dehydrates a row action modal on submit', function () {
    Livewire::test(TafdComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.actions.0.data.status', '')
        ->call('submitActionModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', null, strict: true);
});

it('dehydrates a header action modal on submit', function () {
    Livewire::test(TafdComponent::class)
        ->call('openHeaderActionModal', 'invite')
        ->set('tableState.modal.actions.0.data.status', '')
        ->set('tableState.modal.actions.0.data.quota', '')
        ->call('submitActionModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', null, strict: true)
        ->assertSet('seen.quota', null, strict: true);
});

it('dehydrates a bulk action modal on submit', function () {
    Livewire::test(TafdComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('openBulkActionModal', 'bulkEdit')
        ->set('tableState.modal.actions.0.data.status', '')
        ->call('submitActionModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', null, strict: true);
});

it('passes a chosen value through untouched', function () {
    Livewire::test(TafdComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.actions.0.data.status', 'draft')
        ->call('submitActionModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', 'draft');
});

it('dehydrates a halt modal form before the halted action is re-executed', function () {
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'archive')
        ->assertSet('tableState.modal.halt.show', true)
        // The halt form's fields bind here; this is its live state.
        ->set('tableState.modal.halt.formData.status', '')
        ->set('tableState.modal.halt.formData.quota', '')
        ->call('submitHaltModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', null, strict: true)
        ->assertSet('seen.quota', null, strict: true);
});

it('passes a chosen halt value through untouched', function () {
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'archive')
        ->set('tableState.modal.halt.formData.status', 'draft')
        ->set('tableState.modal.halt.formData.quota', '5')
        ->call('submitHaltModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.status', 'draft')
        ->assertSet('seen.quota', '5');
});

it('leaves a form-less halt exactly as it was', function () {
    // Nothing declares what these keys mean, so nothing may reshape them.
    $test = Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'purge', ['note' => '']);

    $test->call('submitHaltModal')
        ->assertSet('seen.ran', true)
        ->assertSet('seen.note', '');
});

it('keeps the halt form alive past the request that opened it', function () {
    // The confirm is a later request, and the form is not Livewire state: it is
    // restored from a session copy. That copy used to be written *after* the
    // host was bound to the form, and a Livewire component cannot be serialized —
    // so the copy was never written, and by the time anyone confirmed there was
    // no schema left to validate or shape anything with.
    $test = Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'archive')
        ->set('tableState.modal.halt.formData.quota', '7');

    expect($test->instance()->getHaltModalFormInstance())->toBeInstanceOf(Form::class);
});

it("validates the halt form's own field rules on confirm", function () {
    // The confirm submits with no arguments — the view has none to give — which
    // is why validation used to be skipped outright here.
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'close')
        ->set('tableState.modal.halt.formData.reason', '')
        ->call('submitHaltModal')
        ->assertHasErrors('tableState.modal.halt.formData.reason')
        ->assertSet('tableState.modal.halt.show', true)
        ->assertSet('seen', []);
});

it('runs the halted action once its form is valid', function () {
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'close')
        ->set('tableState.modal.halt.formData.reason', 'Duplicate')
        ->call('submitHaltModal')
        ->assertHasNoErrors()
        ->assertSet('seen.ran', true)
        ->assertSet('seen.reason', 'Duplicate')
        ->assertSet('tableState.modal.halt.show', false);
});

it('validates the rules the halt itself declared, with its own message', function () {
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'escalate')
        ->set('tableState.modal.halt.formData.assignee', 'ab')
        ->call('submitHaltModal')
        // Scoped to the field's own path: a field looks itself up by state path,
        // and the confirmation modal has no error summary to fall back on.
        ->assertHasErrors(['tableState.modal.halt.formData.assignee' => 'Too short.'])
        ->assertSet('seen', []);
});

it('runs the halted action when the declared rules pass', function () {
    Livewire::test(TafdComponent::class)
        ->call('executeTableActionWithData', '1', 'escalate')
        ->set('tableState.modal.halt.formData.assignee', 'Ada')
        ->call('submitHaltModal')
        ->assertHasNoErrors()
        ->assertSet('seen.assignee', 'Ada');
});

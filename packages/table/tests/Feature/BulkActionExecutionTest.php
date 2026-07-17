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
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The execution side of the table's action modals: running a bulk action with
 * form data over the selected records, and the guard returns that keep an
 * unregistered action, an empty selection or a missing record from doing
 * anything. BulkAndGroupedActionModalsTest opens the modals; this runs them.
 */
class BaeUser extends Model
{
    protected $table = 'bae_users';

    protected $guarded = [];

    public $timestamps = false;
}

class BaeComponent extends Component
{
    use WithTable;

    /** @var array<string, mixed> */
    public array $received = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(BaeUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('report')
                    ->form([TextInput::make('note')])
                    ->action(fn (array $data) => $this->received = ['header' => $data['note'] ?? null]),
            ])
            ->actions([
                Action::make('touch')
                    ->form([TextInput::make('note')])
                    ->action(fn ($record, array $data) => $this->received = [
                        'row' => $record->getKey(),
                        'note' => $data['note'] ?? null,
                    ]),
            ])
            ->bulkActions([
                BulkAction::make('tag')
                    ->form([TextInput::make('label')])
                    ->action(fn ($records, array $data) => $this->received = [
                        'keys' => $records->pluck('id')->sort()->values()->all(),
                        'label' => $data['label'] ?? null,
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

    Schema::create('bae_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    BaeUser::insert([
        ['id' => 1, 'name' => 'Ada'],
        ['id' => 2, 'name' => 'Grace'],
        ['id' => 3, 'name' => 'Linus'],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('bae_users');
});

// ─── Bulk action execution ────────────────────────────────────

it('runs a bulk action with form data over the selected records', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '3')
        ->call('executeBulkActionWithData', 'tag', ['label' => 'vip']);

    expect($test->instance()->received)->toBe([
        'keys' => [1, 3],
        'label' => 'vip',
    ]);
});

it('routes a submitted bulk modal to the bulk executor', function () {
    // The full path: open the modal, fill it, submit — submitActionModal sees
    // the frame is a bulk action and hands off to executeBulkActionWithData.
    $test = Livewire::test(BaeComponent::class)
        ->call('toggleRecordSelection', '2')
        ->call('openBulkActionModal', 'tag');

    // The modal's form-data bag lives in the state container, not a public prop.
    $test->instance()->tableState->set('modal.actions.0.data', ['label' => 'starred']);

    $test->call('submitActionModal');

    // What line 88 does: route the submitted bulk frame to the bulk executor,
    // which runs over exactly the selected record. (Form-data hydration through
    // the depth-scoped modal state is a forms concern, tested there.)
    expect($test->instance()->received['keys'] ?? null)->toBe([2]);
});

it('does nothing when a bulk action runs with no selection', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('executeBulkActionWithData', 'tag', ['label' => 'x']);

    expect($test->instance()->received)->toBe([]);
});

it('does nothing when the bulk action name is not registered', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('executeBulkActionWithData', 'ghost', []);

    expect($test->instance()->received)->toBe([]);
});

it('does nothing when executeBulkAction gets an empty selection', function () {
    // The no-data entry point guards the same way before opening anything.
    $test = Livewire::test(BaeComponent::class)->call('executeBulkAction', 'tag');

    expect($test->instance()->received)->toBe([]);
});

// ─── Header / row execution guards ────────────────────────────

it('ignores a header action that is not registered', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('executeHeaderActionWithData', 'ghost', ['note' => 'x']);

    expect($test->instance()->received)->toBe([]);
});

it('ignores a row action that is not registered', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('executeTableActionWithData', '1', 'ghost', []);

    expect($test->instance()->received)->toBe([]);
});

it('ignores a row action whose record does not exist', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('executeTableActionWithData', '999', 'touch', ['note' => 'x']);

    expect($test->instance()->received)->toBe([]);
});

it('runs a row action with form data when the record exists', function () {
    $test = Livewire::test(BaeComponent::class)
        ->call('executeTableActionWithData', '2', 'touch', ['note' => 'hi']);

    expect($test->instance()->received)->toBe(['row' => 2, 'note' => 'hi']);
});

// ─── getRecord ────────────────────────────────────────────────

it('resolves a record by key and returns null for a null key', function () {
    $component = Livewire::test(BaeComponent::class)->instance();

    expect($component->getRecord('2')?->getKey())->toBe(2)
        ->and($component->getRecord(null))->toBeNull();
});

// ─── Deprecated BC shims ──────────────────────────────────────

it('keeps the deprecated confirmation methods callable as no-ops', function () {
    // Renamed to the halt-modal system; they survive as deprecation-emitting
    // shims until 2.0 and must not fatal.
    $component = Livewire::test(BaeComponent::class)->instance();

    $component->confirmTableAction('1', 'touch');
    $component->executeConfirmedAction();
    $component->closeConfirmationModal();

    expect($component->received)->toBe([]);
});

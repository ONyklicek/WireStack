<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireTable\Actions\BulkEditAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Set the same value on every selected record, field by field: only what the
 * user switched on is written, and a record the policy keeps them off is
 * counted and left alone.
 */
class BeaTicket extends Model
{
    protected $table = 'bea_tickets';

    protected $guarded = [];

    public $timestamps = false;
}

class BeaComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(BeaTicket::class)
            ->paginated(false)
            ->columns([TextColumn::make('title'), TextColumn::make('status'), TextColumn::make('assignee')])
            ->bulkActions([
                BulkEditAction::make()->fields([
                    Select::make('status')->options(['open' => 'Open', 'closed' => 'Closed']),
                    TextInput::make('assignee'),
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

    Schema::create('bea_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status');
        $table->string('assignee')->nullable();
    });

    BeaTicket::insert([
        ['id' => 1, 'title' => 'Login fails', 'status' => 'open', 'assignee' => 'Eva'],
        ['id' => 2, 'title' => 'Typo', 'status' => 'open', 'assignee' => 'Petr'],
        ['id' => 3, 'title' => 'Slow export', 'status' => 'open', 'assignee' => null],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('bea_tickets');
});

it('writes only the switched-on fields, to the selected records only', function () {
    Livewire::test(BeaComponent::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '3')
        ->call('executeBulkActionWithData', 'bulkEdit', [
            '__change_status' => true,
            'status' => 'closed',
            // Shown, filled, but not switched on: left alone.
            '__change_assignee' => false,
            'assignee' => 'Nobody',
        ]);

    expect(BeaTicket::query()->orderBy('id')->get(['status', 'assignee'])->toArray())->toBe([
        ['status' => 'closed', 'assignee' => 'Eva'],
        ['status' => 'open', 'assignee' => 'Petr'],
        ['status' => 'closed', 'assignee' => null],
    ]);
});

it('draws each field behind its own switch, shown once the switch is on', function () {
    $action = BulkEditAction::make()->fields([TextInput::make('assignee')->label('Assignee')]);
    $schema = $action->getFormSchema();

    expect($schema)->toHaveCount(2)
        ->and($schema[0])->toBeInstanceOf(Toggle::class)
        ->and($schema[0]->getName())->toBe('__change_assignee')
        ->and($schema[0]->getLabel())->toBe('Change Assignee')
        ->and($action->getEditableFields())->toHaveCount(1)
        ->and($action->getLabel())->toBe('Edit selected');
});

it('writes nothing it was not given, and says so', function () {
    $action = BulkEditAction::make()->fields([TextInput::make('assignee')]);

    expect($action->changesFrom(['__change_title' => true, 'title' => 'Hijacked', 'assignee' => 'X']))->toBe([])
        ->and($action->applyTo(BeaTicket::all(), []))->toBe(['updated' => 0, 'skipped' => 0])
        ->and(BeaTicket::query()->where('title', 'Hijacked')->exists())->toBeFalse();
});

it('skips a record the policy will not let the user update, and counts it', function () {
    Gate::policy(BeaTicket::class, get_class(new class
    {
        public function update($user, BeaTicket $ticket): bool
        {
            return $ticket->id !== 2;
        }
    }));
    $this->be(new User);

    $action = BulkEditAction::make()->fields([TextInput::make('assignee')]);
    $result = $action->applyTo(BeaTicket::query()->orderBy('id')->get(), ['__change_assignee' => true, 'assignee' => 'Jana']);

    expect($result)->toBe(['updated' => 2, 'skipped' => 1])
        ->and(BeaTicket::query()->orderBy('id')->pluck('assignee')->all())->toBe(['Jana', 'Petr', 'Jana'])
        ->and($action->getSuccessNotificationMessage())->toBe('2 records updated. 1 was left alone: you may not edit it.');
});

it('reports a single record in the singular', function () {
    $action = BulkEditAction::make()->fields([TextInput::make('assignee')]);
    $action->applyTo(BeaTicket::query()->whereKey(1)->get(), ['__change_assignee' => true, 'assignee' => 'Jana']);

    expect($action->getSuccessNotificationMessage())->toBe('1 record updated.');
});

it('refuses a value the field would refuse, however the data arrived', function () {
    $action = BulkEditAction::make()->fields([
        Select::make('status')->options(['open' => 'Open', 'closed' => 'Closed']),
    ]);

    expect(fn () => $action->applyTo(BeaTicket::all(), ['__change_status' => true, 'status' => 'hacked']))
        ->toThrow(ValidationException::class)
        ->and(BeaTicket::query()->where('status', 'hacked')->exists())->toBeFalse();
});

it('writes nothing through a disabled field, and respects the model\'s guarded attributes', function () {
    $action = BulkEditAction::make()->fields([TextInput::make('assignee')->disabled()]);

    expect($action->changesFrom(['__change_assignee' => true, 'assignee' => 'X']))->toBe([]);

    $guarded = new class extends BeaTicket
    {
        protected $guarded = ['assignee'];
    };

    BulkEditAction::make()->fields([TextInput::make('assignee')])
        ->applyTo($guarded::query()->whereKey(1)->get(), ['__change_assignee' => true, 'assignee' => 'Nope']);

    expect(BeaTicket::find(1)->assignee)->toBe('Eva');
});

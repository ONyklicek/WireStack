<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * "Deleted 2 records · Undo": an undoable() action restores what it
 * soft-deleted, for the component and the user that did it, inside the window.
 */
class UaNote extends Model
{
    use SoftDeletes;

    protected $table = 'ua_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class UaHardNote extends Model
{
    protected $table = 'ua_notes';

    protected $guarded = [];

    public $timestamps = false;
}

class UaTable extends Component
{
    use WithTable;

    public string $model = UaNote::class;

    public function table(Table $table): Table
    {
        return $table
            ->model($this->model)
            ->paginated(false)
            ->columns([TextColumn::make('title')])
            ->actions([
                Action::make('trash')->undoable()->action(fn (Model $record) => $record->delete()),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->undoable(30)->action(fn ($records) => $records->each->delete()),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('ua_notes', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->softDeletes();
    });

    UaNote::insert([['id' => 1, 'title' => 'One'], ['id' => 2, 'title' => 'Two'], ['id' => 3, 'title' => 'Three']]);
});

afterEach(function () {
    Schema::dropIfExists('ua_notes');
});

/** The token the last undo offer was stored under. */
function uaToken(): ?string
{
    foreach ((fn () => $this->storage)->call(Cache::store()->getStore()) as $key => $value) {
        if (str_starts_with((string) $key, 'wire-table-undo:')) {
            return substr((string) $key, strlen('wire-table-undo:'));
        }
    }

    return null;
}

it('offers to restore what a bulk delete soft-deleted, and restores it', function () {
    $table = Livewire::test(UaTable::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '3')
        ->call('executeBulkAction', 'delete', true);

    expect(UaNote::query()->pluck('id')->all())->toBe([2])
        ->and($token = uaToken())->not->toBeNull();

    $table->call('undoTableAction', $token);

    expect(UaNote::query()->orderBy('id')->pluck('id')->all())->toBe([1, 2, 3])
        // One use: the offer is gone.
        ->and(uaToken())->toBeNull();
});

it('restores a row action too, and only for its own component and user', function () {
    $table = Livewire::test(UaTable::class)->call('executeTableAction', '2', 'trash', true);
    $token = uaToken();

    // Another instance of the same table hears the event and leaves it alone.
    Livewire::test(UaTable::class)->call('undoTableAction', $token);
    expect(UaNote::query()->whereKey(2)->exists())->toBeFalse();

    // An offer someone else made, a forged token: nothing.
    Cache::put('wire-table-undo:forged', ['owner' => 'x', 'user' => null, 'model' => UaNote::class, 'keys' => [2]], 10);
    $table->call('undoTableAction', 'forged')->call('undoTableAction', 'nope');
    expect(UaNote::query()->whereKey(2)->exists())->toBeFalse();
});

it('asks the restore policy for each record', function () {
    Gate::policy(UaNote::class, get_class(new class
    {
        public function restore($user, UaNote $note): bool
        {
            return $note->id !== 1;
        }
    }));
    $this->be(new User);

    $table = Livewire::test(UaTable::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '2')
        ->call('executeBulkAction', 'delete', true);

    $table->call('undoTableAction', uaToken());

    expect(UaNote::query()->orderBy('id')->pluck('id')->all())->toBe([2, 3]);
});

it('offers nothing when the records are gone for good', function () {
    Livewire::test(UaTable::class, ['model' => UaHardNote::class])->call('executeTableAction', '1', 'trash', true);

    expect(uaToken())->toBeNull()
        ->and(UaHardNote::query()->count())->toBe(2);
});

it('keeps the setting on the action', function () {
    expect(Action::make('x')->getUndoSeconds())->toBeNull()
        ->and(Action::make('x')->undoable()->getUndoSeconds())->toBe(10)
        ->and(Action::make('x')->undoable(0)->getUndoSeconds())->toBe(1)
        ->and(Action::make('x')->undoable(5)->undoable(false)->getUndoSeconds())->toBeNull();
});

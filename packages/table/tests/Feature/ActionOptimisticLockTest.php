<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A modal action's window is the long one: it opens, the user reads the record,
 * types, and submits some time later. An inline edit carries the version it
 * rendered with and is refused if the row moved; the modal path carried nothing
 * and overwrote whatever had happened in between.
 *
 * optimisticLock() closes exactly that window — from the frame being pushed to
 * the submit — and stays off by default, because "someone else touched this row"
 * only invalidates an action that decided something from what it read.
 */
class ActionLockDoc extends Model
{
    protected $table = 'action_lock_docs';

    protected $guarded = [];
}

class ActionLockHost extends Component
{
    use WithTable;

    public bool $locked = true;

    /** Set by the action so a test can tell whether it ran at all. */
    public string $ranWith = '';

    public function table(Table $table): Table
    {
        $approve = Action::make('approve')
            ->label('Approve')
            ->modalHeading('Approve')
            ->form(fn () => [])
            ->action(function (ActionLockDoc $record): void {
                $this->ranWith = $record->title;
                $record->update(['status' => 'approved']);
            });

        if ($this->locked) {
            $approve->optimisticLock();
        }

        return $table
            ->model(ActionLockDoc::class)
            ->columns([TextColumn::make('title')])
            ->actions([$approve]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('action_lock_docs', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->string('status')->default('draft');
        $t->timestamps();
    });

    ActionLockDoc::create(['id' => 1, 'title' => 'Invoice 1']);
});

afterEach(function () {
    Schema::dropIfExists('action_lock_docs');
});

/** Move the row on, the way another session would. */
function actionLockTouchRecord(): void
{
    DB::table('action_lock_docs')->where('id', 1)->update([
        'title' => 'Invoice 1 (edited by someone else)',
        'updated_at' => now()->addMinutes(5),
    ]);
}

it('runs a locked action when nothing touched the record', function () {
    $c = Livewire::test(ActionLockHost::class)
        ->call('openActionModal', '1', 'approve')
        ->call('executeTableActionWithData', '1', 'approve', []);

    expect(ActionLockDoc::find(1)->status)->toBe('approved');
    expect($c->get('ranWith'))->toBe('Invoice 1');
});

it('refuses a locked action whose record moved while the modal was open', function () {
    $c = Livewire::test(ActionLockHost::class)->call('openActionModal', '1', 'approve');

    actionLockTouchRecord();

    $c->call('executeTableActionWithData', '1', 'approve', []);

    expect(ActionLockDoc::find(1)->status)->toBe('draft');
    expect($c->get('ranWith'))->toBe('');
});

it('closes the modal when it refuses, rather than leaving a stale form up', function () {
    $c = Livewire::test(ActionLockHost::class)->call('openActionModal', '1', 'approve');

    actionLockTouchRecord();

    $c->call('executeTableActionWithData', '1', 'approve', []);

    expect($c->get('tableState')->get('modal.actions'))->toBe([]);
    expect($c->get('tableState')->get('modal.open'))->toBeFalse();
});

it('leaves an unlocked action alone — the default is off', function () {
    $c = Livewire::test(ActionLockHost::class, ['locked' => false])
        ->call('openActionModal', '1', 'approve');

    actionLockTouchRecord();

    $c->call('executeTableActionWithData', '1', 'approve', []);

    expect(ActionLockDoc::find(1)->status)->toBe('approved');
    expect($c->get('ranWith'))->toBe('Invoice 1 (edited by someone else)');
});

it('does not refuse when the action was mounted before a baseline was captured', function () {
    // A frame pushed by an older release carries no version. There is nothing to
    // compare, so the action runs — the same reading RecordVersion gives a cell
    // whose client sent no version.
    $c = Livewire::test(ActionLockHost::class)
        ->call('openActionModal', '1', 'approve')
        ->set('tableState.modal.actions.0.recordVersion', null);

    actionLockTouchRecord();

    $c->call('executeTableActionWithData', '1', 'approve', []);

    expect(ActionLockDoc::find(1)->status)->toBe('approved');
});

it('shares one answer to "has this row moved" with the inline edit', function () {
    // Not a separate convention: the same RecordVersion object the cell edit
    // compares with, so the two cannot drift.
    $record = ActionLockDoc::find(1);
    $version = app(RecordVersion::class)->stamp($record);

    $c = Livewire::test(ActionLockHost::class)->call('openActionModal', '1', 'approve');

    expect($c->get('tableState')->get('modal.actions.0.recordVersion'))->toBe($version);
});

it('does not compare one record against another record\'s baseline', function () {
    // The stack holds the frame for record 1; a submit arrives for record 2. The
    // two versions have no reason to agree, and comparing them would report a
    // conflict for a record nothing had touched.
    ActionLockDoc::create(['id' => 2, 'title' => 'Invoice 2']);

    // Give record 1 a version that differs from record 2's.
    DB::table('action_lock_docs')->where('id', 1)->update(['updated_at' => now()->addHour()]);

    $c = Livewire::test(ActionLockHost::class)->call('openActionModal', '1', 'approve');

    $c->call('executeTableActionWithData', '2', 'approve', []);

    expect(ActionLockDoc::find(2)->status)->toBe('approved');
});

it('still refuses when the frame is for the record being submitted', function () {
    // The guard above must not become a way to opt out of the lock entirely.
    $c = Livewire::test(ActionLockHost::class)->call('openActionModal', '1', 'approve');

    actionLockTouchRecord();

    $c->call('executeTableActionWithData', '1', 'approve', []);

    expect(ActionLockDoc::find(1)->status)->toBe('draft');
});

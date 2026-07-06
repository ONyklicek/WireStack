<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\Runtime\StaleModelException;

beforeEach(function () {
    Schema::dropIfExists('ol_records');

    Schema::create('ol_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('version')->default(1);
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('ol_records');
});

function olForm(Model $record): Form
{
    return Form::make()
        ->model($record)
        ->optimisticLock()
        ->schema([TextInput::make('name')->required()])
        ->fill(['name' => $record->name]);
}

test('save succeeds when the record was not changed concurrently', function () {
    $record = OlRecordModel::create(['name' => 'Original']);

    $form = olForm($record);
    $form->fill(['name' => 'Edited']);

    $form->save();

    expect($record->fresh()->name)->toBe('Edited');
});

test('save aborts with StaleModelException when updated_at changed concurrently', function () {
    $record = OlRecordModel::create(['name' => 'Original']);

    // Form is opened; baseline captured from the record's current updated_at.
    $form = olForm($record);
    $form->fill(['name' => 'My edit']);

    // Someone else saves the same record in the meantime.
    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    expect(fn () => $form->save())->toThrow(StaleModelException::class);

    // The stale write must NOT have overwritten the concurrent change.
    expect($record->fresh()->name)->toBe('Original');
});

test('lock is opt-in: without optimisticLock() a concurrent change is overwritten', function () {
    $record = OlRecordModel::create(['name' => 'Original']);

    $form = Form::make()
        ->model($record)
        ->schema([TextInput::make('name')->required()])
        ->fill(['name' => 'My edit']);

    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    $form->save();

    expect($record->fresh()->name)->toBe('My edit');
});

test('works with a custom integer version column', function () {
    $record = OlRecordModel::create(['name' => 'Original', 'version' => 1]);

    $form = Form::make()
        ->model($record)
        ->optimisticLock('version')
        ->schema([TextInput::make('name')->required()])
        ->fill(['name' => 'My edit']);

    // Concurrent bump of the version column.
    OlRecordModel::query()->whereKey($record->getKey())->update(['version' => 2]);

    expect(fn () => $form->save())->toThrow(StaleModelException::class);
    expect($record->fresh()->name)->toBe('Original');
});

test('the exception carries the model and lock column', function () {
    $record = OlRecordModel::create(['name' => 'Original']);
    $form = olForm($record);
    $form->fill(['name' => 'My edit']);

    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    try {
        $form->save();
        expect()->fail('Expected StaleModelException');
    } catch (StaleModelException $e) {
        expect($e->lockColumn)->toBe('updated_at')
            ->and($e->model->getKey())->toBe($record->getKey());
    }
});

test('fails open when no baseline was captured (model set after fill)', function () {
    $record = OlRecordModel::create(['name' => 'Original']);

    // model() after fill() → baseline cannot be captured, so the guard has no
    // reference point and must not block the save (documented ordering caveat).
    $form = Form::make()
        ->optimisticLock()
        ->schema([TextInput::make('name')->required()])
        ->fill(['name' => 'My edit'])
        ->model($record);

    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    $form->save();

    expect($record->fresh()->name)->toBe('My edit');
});

test('conflict still throws when no notification manager is bound', function () {
    app()->offsetUnset(NotificationManager::class);

    $record = OlRecordModel::create(['name' => 'Original']);
    $form = olForm($record);
    $form->fill(['name' => 'My edit']);

    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    expect(fn () => $form->save())->toThrow(StaleModelException::class);
    expect($record->fresh()->name)->toBe('Original');
});

test('a conflict emits an error notification', function () {
    $driver = new class implements NotificationDriver
    {
        /** @var array<int, Notification> */
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };

    app()->instance(NotificationManager::class, new NotificationManager);
    NotificationManager::setDefaultDriver($driver);

    $record = OlRecordModel::create(['name' => 'Original']);
    $form = olForm($record);
    $form->fill(['name' => 'My edit']);

    OlRecordModel::query()->whereKey($record->getKey())
        ->update(['updated_at' => now()->addMinutes(5)]);

    expect(fn () => $form->save())->toThrow(StaleModelException::class);

    expect($driver->sent)->toHaveCount(1)
        ->and($driver->sent[0]->type)->toBe('error')
        ->and($driver->sent[0]->message)->toBe(trans('wire-forms::messages.stale'));

    NotificationManager::reset();
});

class OlRecordModel extends Model
{
    protected $table = 'ol_records';

    protected $guarded = [];
}

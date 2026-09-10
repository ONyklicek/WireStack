<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Drivers\BroadcastDriver;
use NyonCode\WireCore\Notifications\Drivers\DatabaseDriver;
use NyonCode\WireCore\Notifications\Events\NotificationReceived;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;
use NyonCode\WireCore\Notifications\NotificationManager;

/*
 * Saying who a notification is for, and what survives being stored.
 *
 * The two halves of the same complaint: the drivers that write a notification
 * down are the ones a queued job uses, and a queued job has nobody logged in and
 * a recipient it was handed as an argument. Before `to()` the only way to say so
 * was to rebind ResolvesNotifiable inside the job — which the documentation
 * taught, which is the sign of an API that cannot express its own main case.
 */
class AnUser extends Model
{
    protected $table = 'an_users';

    protected $guarded = [];

    public $timestamps = false;
}

/** Nobody is signed in — a queue worker, which is the whole point. */
function noRecipient(): ResolvesNotifiable
{
    return new class implements ResolvesNotifiable
    {
        public function resolve(): ?Model
        {
            return null;
        }
    };
}

beforeEach(function () {
    Schema::create('an_users', function (Blueprint $t) {
        $t->id();
    });

    Schema::create('wire_notifications', function (Blueprint $t) {
        $t->ulid('id')->primary();
        $t->string('type');
        $t->string('notifiable_type');
        $t->string('notifiable_id');
        $t->json('data');
        $t->timestamp('read_at')->nullable();
        $t->timestamps();
    });

    $this->ada = AnUser::create(['id' => 1]);
    $this->grace = AnUser::create(['id' => 2]);
});

afterEach(function () {
    Schema::dropIfExists('wire_notifications');
    Schema::dropIfExists('an_users');
});

it('writes the row for whoever the notification names, with nobody signed in', function () {
    (new DatabaseDriver(noRecipient()))->send(
        Notification::success('Your export is ready')->to($this->grace),
    );

    $row = DatabaseNotification::query()->sole();

    expect($row->notifiable_id)->toBe('2')
        ->and($row->notifiable_type)->toBe(AnUser::class);
});

it('addresses several people from one job without rebinding anything', function () {
    $driver = new DatabaseDriver(noRecipient());

    foreach ([$this->ada, $this->grace] as $user) {
        $driver->send(Notification::info('Payroll ran')->to($user));
    }

    expect(DatabaseNotification::query()->pluck('notifiable_id')->all())->toEqual(['1', '2']);
});

it('broadcasts on the named recipient channel, not on the session one', function () {
    Event::fake();

    (new BroadcastDriver(noRecipient()))->send(Notification::info('hi')->to($this->grace));

    Event::assertDispatched(
        NotificationReceived::class,
        fn (NotificationReceived $e): bool => $e->channel === 'wire-notifications.AnUser.2',
    );
});

it('still falls back to the resolver when the notification names nobody', function () {
    $driver = new DatabaseDriver(new class($this->ada) implements ResolvesNotifiable
    {
        public function __construct(private Model $user) {}

        public function resolve(): ?Model
        {
            return $this->user;
        }
    });

    $driver->send(Notification::success('Saved'));

    expect(DatabaseNotification::query()->sole()->notifiable_id)->toBe('1');
});

it('keeps the recipient out of the stored payload', function () {
    // `toArray()` is what the notification *says*; the recipient is which row it
    // is stored in. A user id inside the JSON every reader treats as content is
    // a leak waiting for the first consumer that renders the payload.
    $payload = Notification::success('Saved')->to($this->grace)->toArray();

    expect($payload)->not->toHaveKey('notifiable')
        ->and(json_encode($payload))->not->toContain('AnUser');
});

it('sends to a recipient in one call', function () {
    config()->set('wire-core.notifications.default', 'database');
    NotificationManager::reset();

    NotificationManager::sendTo($this->grace, Notification::info('hi'), app(NotificationDriver::class));

    expect(DatabaseNotification::query()->sole()->notifiable_id)->toBe('2');
});

/*
 * ─── The round trip ─────────────────────────────────────────────
 */

it('reads a stored notification back as the notification it was raised as', function () {
    $raised = Notification::warning('Payment needs review')
        ->title('Action required')
        ->icon('outline:banknotes')
        ->duration(0)
        ->position('top-left')
        ->url('/invoices/42')
        ->extra(['invoice' => 42])
        ->action(NotificationAction::link('Open invoice', '/invoices/42'))
        ->action(NotificationAction::make('Undo', 'restore')->payload(['id' => 42])->color('primary'))
        ->to($this->ada);

    (new DatabaseDriver(noRecipient()))->send($raised);

    $read = DatabaseNotification::query()->sole()->toNotification();

    // The whole payload, because the driver writes the whole payload: anything
    // missing here is written and then unreadable, which is what happened to the
    // actions for as long as they were not restored.
    expect($read->type)->toBe('warning')
        ->and($read->message)->toBe('Payment needs review')
        ->and($read->title)->toBe('Action required')
        ->and($read->icon)->toBe('outline:banknotes')
        ->and($read->duration)->toBe(0)
        ->and($read->position)->toBe('top-left')
        ->and($read->url)->toBe('/invoices/42')
        ->and($read->extra)->toBe(['invoice' => 42])
        ->and($read->actions)->toHaveCount(2)
        ->and($read->actions[0]->label)->toBe('Open invoice')
        ->and($read->actions[0]->url)->toBe('/invoices/42')
        ->and($read->actions[1]->event)->toBe('restore')
        ->and($read->actions[1]->payload)->toBe(['id' => 42])
        ->and($read->actions[1]->color)->toBe('primary');
});

it('keeps the good actions in a payload that also has a bad one', function () {
    // The payload is a row: an older version of the application, or a hand
    // edit. Three good actions and one nonsense should show three buttons.
    $row = DatabaseNotification::query()->create([
        'id' => (string) Str::ulid(),
        'type' => 'test',
        'notifiable_type' => AnUser::class,
        'notifiable_id' => '1',
        'data' => ['type' => 'info', 'message' => 'x', 'actions' => [
            ['label' => 'Good', 'url' => '/somewhere'],
            ['event' => 'no-label-so-not-an-action'],
            'not even an array',
        ]],
        'read_at' => null,
    ]);

    expect($row->toNotification()->actions)->toHaveCount(1);
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Drivers\DatabaseDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationCenter;

/*
 * The bell.
 *
 * It holds no query of its own and reads NotificationCenter, so the recipient
 * scoping — the thing that stops one user seeing another's rows — has one
 * owner. What is worth asserting here is the rendering and the two actions.
 */
class NbUser extends Model
{
    protected $table = 'nb_users';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('nb_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    Schema::create('wire_notifications', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('type');
        $t->string('notifiable_type');
        $t->string('notifiable_id');
        $t->json('data');
        $t->timestamp('read_at')->nullable();
        $t->timestamps();
    });

    $this->ada = NbUser::create(['id' => 1, 'name' => 'Ada']);

    // The application's answer to "who is this for", bound so both the driver
    // and the bell resolve the same recipient.
    app()->bind(ResolvesNotifiable::class, fn () => new class($this->ada) implements ResolvesNotifiable
    {
        public function __construct(private Model $user) {}

        public function resolve(): ?Model
        {
            return $this->user;
        }
    });

    app()->bind(NotificationCenter::class, fn ($app) => new NotificationCenter($app->make(ResolvesNotifiable::class)));

    $this->driver = new DatabaseDriver(app(ResolvesNotifiable::class));
});

afterEach(function () {
    Schema::dropIfExists('wire_notifications');
    Schema::dropIfExists('nb_users');
});

it('shows the unread count and the notifications behind it', function () {
    $this->driver->send(Notification::success('Export ready')->title('Invoices'));
    $this->driver->send(Notification::error('Import failed'));

    Livewire::test('wire-notification-bell')
        ->assertSee('Export ready')
        ->assertSee('Invoices')
        ->assertSee('Import failed')
        ->assertSeeHtml('data-testid="notification-bell-count"');
});

it('shows no count when everything has been read', function () {
    // A zero badge is noise: the bell's job is to say something is waiting.
    $this->driver->send(Notification::success('Export ready'));
    app(NotificationCenter::class)->markAllAsRead();

    Livewire::test('wire-notification-bell')
        ->assertDontSeeHtml('data-testid="notification-bell-count"')
        // Read notifications stay in the list, they just stop counting.
        ->assertSee('Export ready');
});

it('says so when there is nothing', function () {
    Livewire::test('wire-notification-bell')
        ->assertSee('Nothing here yet.')
        ->assertDontSeeHtml('data-testid="notification-bell-count"');
});

it('marks one read from the list', function () {
    $this->driver->send(Notification::success('Export ready'));
    $id = DatabaseNotification::query()->sole()->id;

    Livewire::test('wire-notification-bell')
        ->call('markAsRead', $id)
        ->assertDontSeeHtml('data-testid="notification-bell-count"');

    expect(DatabaseNotification::query()->sole()->isRead())->toBeTrue();
});

it('marks everything read at once', function () {
    $this->driver->send(Notification::success('one'));
    $this->driver->send(Notification::success('two'));

    Livewire::test('wire-notification-bell')
        ->call('markAllAsRead')
        ->assertDontSeeHtml('data-testid="notification-mark-all"');

    expect(app(NotificationCenter::class)->unreadCount())->toBe(0);
});

it('caps the list without capping the count', function () {
    // The dropdown shows a few; the badge has to tell the truth about the rest.
    foreach (range(1, 5) as $i) {
        $this->driver->send(Notification::success("note {$i}"));
    }

    Livewire::test('wire-notification-bell', ['limit' => 2])
        // The badge tells the truth about all five…
        ->assertSee('5')
        // …while the list shows the newest two. All five land in the same
        // second, so this only holds because the id breaks the tie.
        ->assertSee('note 5')
        ->assertSee('note 4')
        ->assertDontSee('note 1');
});

it('re-reads when told a notification landed', function () {
    // The listener exists so an application that knows one arrived can say so;
    // re-rendering is the refresh, which is why the handler has no body.
    $this->driver->send(Notification::success('Export ready'));

    Livewire::test('wire-notification-bell')
        ->dispatch('wire-notification-received')
        ->assertSee('Export ready');
});

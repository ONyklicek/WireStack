<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Drivers\DatabaseDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;
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

/*
 * ─── The panel ──────────────────────────────────────────────────
 *
 * A slide-over rather than a 320px dropdown, and the tabs that come with the
 * room: an inbox is a place you go, not a menu you brush past.
 */

it('opens a panel rather than a dropdown', function () {
    Livewire::test('wire-notification-bell')
        // The Rule-5 Htmlable slide-over, entangled with the component's own
        // state so the trigger is a single round trip rather than Alpine state
        // the server cannot see.
        ->assertSeeHtml('wire-modal-slideover-wire-notifications')
        ->assertSeeHtml('data-testid="notification-tab-all"')
        ->assertSeeHtml('data-testid="notification-tab-unread"');
});

it('hides what has been read on the unread tab, and says so', function () {
    $this->driver->send(Notification::success('Export ready'));
    $this->driver->send(Notification::success('Import finished'));
    app(NotificationCenter::class)->markAsRead(
        DatabaseNotification::query()->where('data->message', 'Export ready')->sole()->id,
    );

    Livewire::test('wire-notification-bell')
        ->call('setTab', 'unread')
        ->assertSee('Import finished')
        ->assertDontSee('Export ready');
});

it('says something different when the unread tab is the empty one', function () {
    // "Nothing here yet" is wrong under a filter that hides everything: there is
    // something here, it has just all been read.
    $this->driver->send(Notification::success('Export ready'));
    app(NotificationCenter::class)->markAllAsRead();

    Livewire::test('wire-notification-bell')
        ->call('setTab', 'unread')
        ->assertSee('Nothing unread.')
        ->assertDontSee('Nothing here yet.');
});

it('answers a tab it does not have with the one it starts on', function () {
    // The argument arrives from a Livewire call, which is user input.
    Livewire::test('wire-notification-bell')
        ->call('setTab', 'archived')
        ->assertSet('tab', 'all');
});

it('tints each row by what kind of notification it is', function () {
    $this->driver->send(Notification::error('Import failed'));
    $this->driver->send(Notification::success('Export ready'));

    $html = Livewire::test('wire-notification-bell')->html();

    // Resolved in PHP, because the view may not branch on domain state — see
    // NotificationStyle for the mapping itself.
    expect($html)->toContain('text-red-500')
        ->and($html)->toContain('text-emerald-500');
});

/*
 * ─── Live, and the link out ─────────────────────────────────────
 */

it('carries no live bridge when nothing is broadcasting', function () {
    // The ordinary configuration: no channel to name, so no channel to
    // authorize and no bundle to ship.
    Livewire::test('wire-notification-bell')
        ->assertDontSeeHtml('wireNotificationLive(');
});

it('subscribes to the recipient own channel when the broadcast driver is on', function () {
    config()->set('wire-core.notifications.default', ['database', 'broadcast']);

    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('wireNotificationLive(')
        ->assertSeeHtml('wire-notifications.NbUser.1');
});

it('links to the full list only where one is routed', function () {
    // wire-core routes nothing; wire-panels answers ResolvesPageUrls, and
    // wire-module-notifications is what registers the `notifications` key. Absent
    // both, the panel renders without the link rather than with a broken one.
    Livewire::test('wire-notification-bell')
        ->assertDontSeeHtml('data-testid="notification-view-all"');

    app()->bind(ResolvesPageUrls::class, fn () => new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return $page === 'index' ? '/admin/notifications' : '/admin/notifications/'.$parameters['record'];
        }
    });

    $this->driver->send(Notification::success('Export ready'));

    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('data-testid="notification-view-all"')
        ->assertSeeHtml('href="/admin/notifications"')
        // And each row is the link to its own page, which is what marks it read.
        ->assertSeeHtml('/admin/notifications/');
});

/*
 * ─── The verbs, and where a row goes ────────────────────────────
 */

it('marks a notification unread again', function () {
    // "I will deal with this later" is a decision a person makes after opening
    // something; without this the only way back is to remember it existed.
    $this->driver->send(Notification::success('Export ready'));
    app(NotificationCenter::class)->markAllAsRead();
    $id = DatabaseNotification::query()->sole()->id;

    Livewire::test('wire-notification-bell')
        ->call('markAsUnread', $id)
        ->assertSeeHtml('data-testid="notification-bell-count"');

    expect(DatabaseNotification::query()->sole()->isRead())->toBeFalse();
});

it('deletes one from the list', function () {
    $this->driver->send(Notification::success('Export ready'));
    $id = DatabaseNotification::query()->sole()->id;

    Livewire::test('wire-notification-bell')->call('delete', $id);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('clears what has been read and keeps what has not', function () {
    // The bulk verb that is safe to offer: it cannot lose the user something
    // they have not looked at, which "delete all" can — and is why there is no
    // button for that.
    $this->driver->send(Notification::success('seen'));
    $this->driver->send(Notification::success('unseen'));
    app(NotificationCenter::class)->markAsRead(
        DatabaseNotification::query()->where('data->message', 'seen')->sole()->id,
    );

    Livewire::test('wire-notification-bell')->call('clearRead');

    expect(DatabaseNotification::query()->pluck('data')->pluck('message')->all())->toBe(['unseen']);
});

it('will not let one viewer touch another viewer s notification', function () {
    // Every verb goes through NotificationCenter::find(), which is scoped. An id
    // arriving from a Livewire call is user input.
    $other = NbUser::create(['id' => 2, 'name' => 'Grace']);
    $theirs = DatabaseNotification::query()->create([
        'id' => (string) Str::ulid(),
        'type' => 'test',
        'notifiable_type' => NbUser::class,
        'notifiable_id' => (string) $other->getKey(),
        'data' => ['message' => 'not yours'],
        'read_at' => null,
    ]);

    Livewire::test('wire-notification-bell')
        ->call('markAsRead', $theirs->id)
        ->call('delete', $theirs->id)
        ->call('open', $theirs->id);

    $still = DatabaseNotification::query()->find($theirs->id);

    expect($still)->not->toBeNull()
        ->and($still->isRead())->toBeFalse();
});

it('follows a notification to what it is about, marking it read on the way', function () {
    $this->driver->send(Notification::success('Invoice paid')->url('/invoices/42'));
    $id = DatabaseNotification::query()->sole()->id;

    Livewire::test('wire-notification-bell')
        ->call('open', $id)
        ->assertRedirect('/invoices/42');

    expect(DatabaseNotification::query()->sole()->isRead())->toBeTrue();
});

it('links the row at the thing, not at the notification, when it says where', function () {
    // Its own page is the fallback; what the reader wants is the invoice.
    app()->bind(ResolvesPageUrls::class, fn () => new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return '/admin/notifications';
        }
    });

    $this->driver->send(Notification::success('Invoice paid')->url('/invoices/42'));

    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('href="/invoices/42"');
});

it('renders a stored action as a link that still works days later', function () {
    // An event reaches a Livewire listener that has to be on the page; a link
    // survives the request that raised it, which is what a stored notification
    // needs.
    $this->driver->send(
        Notification::success('Export ready')
            ->action(NotificationAction::link('Download', '/exports/7.csv'))
            ->action(NotificationAction::make('Undo', 'restore-export')),
    );

    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('href="/exports/7.csv"')
        ->assertSee('Download')
        // The event kind is rendered too — an application whose listener is
        // mounted is entitled to it.
        ->assertSee('Undo')
        ->assertSeeHtml('restore-export');
});

it('links back into the zone the page was rendered in', function () {
    // ADR 0027: a zone is a route-name prefix, and every URL question carries
    // it. The bell reads it once at mount and carries it, because Zone::current()
    // answers `livewire.update` on the round trips every verb here makes.
    app()->bind(ResolvesPageUrls::class, fn () => new class implements ResolvesPageUrls
    {
        public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
        {
            return $zone === null ? null : "/{$zone}/notifications";
        }
    });

    $this->driver->send(Notification::success('Export ready'));

    Livewire::test('wire-notification-bell', ['zone' => 'admin'])
        ->assertSet('zone', 'admin')
        ->assertSeeHtml('href="/admin/notifications"');

    // And an application that mounts nothing in a zone still gets a panel — one
    // without links, rather than a broken one.
    Livewire::test('wire-notification-bell')
        ->assertDontSeeHtml('data-testid="notification-view-all"');
});

it('colours an action button from the vocabulary the toast already knows', function () {
    // An action written once must not mean one thing in a toast and something
    // else in the panel three days later; NotificationStyle owns the six names.
    $this->driver->send(
        Notification::info('Deploy finished')
            ->action(NotificationAction::link('Rollback', '/rollback')->color('danger'))
            ->action(NotificationAction::link('Release notes', '/notes')),
    );

    $html = Livewire::test('wire-notification-bell')->html();

    expect($html)->toContain('bg-red-50')
        // The one that named no colour falls back to the notification's own,
        // which is what the toast does.
        ->and($html)->toContain('bg-cyan-50');
});

/*
 * ─── The mark, and reading the list ─────────────────────────────
 */

it('marks the bell three ways, because there are three things to say', function () {
    // A bell with no badge cannot tell "nothing has ever happened" from "you
    // have read everything", and those deserve different marks.
    Livewire::test('wire-notification-bell')
        ->assertDontSeeHtml('data-testid="notification-bell-count"')
        ->assertDontSeeHtml('data-testid="notification-bell-dot"');

    $this->driver->send(Notification::success('Export ready'));

    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('data-testid="notification-bell-count"')
        ->assertDontSeeHtml('data-testid="notification-bell-dot"');

    app(NotificationCenter::class)->markAllAsRead();

    Livewire::test('wire-notification-bell')
        // Quiet, not absent: something is in there, nothing is waiting.
        ->assertSeeHtml('data-testid="notification-bell-dot"')
        ->assertDontSeeHtml('data-testid="notification-bell-count"');
});

it('says the count in the button name, not only in a coloured circle', function () {
    $this->driver->send(Notification::success('one'));
    $this->driver->send(Notification::success('two'));

    // A badge is decoration to a screen reader, and "Notifications" alone loses
    // the one thing the bell is there to say.
    Livewire::test('wire-notification-bell')
        ->assertSeeHtml('— 2')
        // …and the badge itself is then hidden, or the number is read twice.
        ->assertSeeHtml('aria-hidden="true"');
});

it('files the list under the day each notification landed on', function () {
    $this->driver->send(Notification::success('from today'));

    DatabaseNotification::query()->create([
        'id' => (string) Str::ulid(),
        'type' => 'test',
        'notifiable_type' => NbUser::class,
        'notifiable_id' => '1',
        'data' => ['message' => 'from last week'],
        'read_at' => null,
        'created_at' => now()->subWeek(),
        'updated_at' => now()->subWeek(),
    ]);

    Livewire::test('wire-notification-bell')
        ->assertSee('Today')
        ->assertSee('Earlier');
});

it('reads all as a timeline rather than floating the unread to the top', function () {
    // The ordering used to put unread first, to stop a burst of reads pushing an
    // old unread item off a ten-row list. The Unread tab answers that properly
    // and completely; what the ordering cost was a list nobody could read as a
    // timeline, with Monday above Thursday and day headings repeating.
    $this->driver->send(Notification::success('older'));
    $this->driver->send(Notification::success('newer'));

    app(NotificationCenter::class)->markAsRead(
        DatabaseNotification::query()->where('data->message', 'newer')->sole()->id,
    );

    $html = Livewire::test('wire-notification-bell')->html();

    expect(strpos($html, 'newer'))->toBeLessThan(strpos($html, 'older'));
});

it('says something useful when a tab is empty, and something different per tab', function () {
    Livewire::test('wire-notification-bell')
        ->assertSee('Nothing here yet.');

    $this->driver->send(Notification::success('Export ready'));
    app(NotificationCenter::class)->markAllAsRead();

    Livewire::test('wire-notification-bell')
        ->call('setTab', 'unread')
        // Not "nothing here yet" — there is something here, it has all been read.
        ->assertSee('Everything is read');
});

it('does not print the same sentence twice in two weights', function () {
    // An application that passes the same string as title and message should not
    // get it rendered twice as though the repetition meant something.
    $this->driver->send(Notification::success('Export ready')->title('Export ready'));

    $html = Livewire::test('wire-notification-bell')->html();

    expect(substr_count($html, 'Export ready'))->toBe(1);
});

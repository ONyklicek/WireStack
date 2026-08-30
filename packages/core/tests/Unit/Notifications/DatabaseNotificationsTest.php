<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Notifications\AuthenticatedNotifiable;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Drivers\DatabaseDriver;
use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;
use NyonCode\WireCore\Notifications\Drivers\StackDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationCenter;

/*
 * Notifications that outlive the request that raised them.
 *
 * The transient drivers hand a message to the page being rendered, which is the
 * right answer for "saved" and the wrong one for a queued export finishing
 * twenty minutes later — by then there is no component to dispatch to and no
 * session to flash into.
 */
/** A Model that is also Authenticatable — the shape an application's User has. */
class DnAuthUser extends User
{
    protected $table = 'dn_users';

    protected $guarded = [];

    public $timestamps = false;
}

class DnUser extends Model
{
    protected $table = 'dn_users';

    protected $guarded = [];

    public $timestamps = false;
}

/** The application's answer to "who is this for", stubbed. */
function dnResolver(?Model $user): ResolvesNotifiable
{
    return new class($user) implements ResolvesNotifiable
    {
        public function __construct(private ?Model $user) {}

        public function resolve(): ?Model
        {
            return $this->user;
        }
    };
}

beforeEach(function () {
    Schema::create('dn_users', function (Blueprint $t) {
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

    $this->ada = DnUser::create(['id' => 1, 'name' => 'Ada']);
    $this->grace = DnUser::create(['id' => 2, 'name' => 'Grace']);
});

afterEach(function () {
    Schema::dropIfExists('wire_notifications');
    Schema::dropIfExists('dn_users');
});

// ─── Writing ─────────────────────────────────────────────────────────────────

it('writes the whole payload, not just type and message', function () {
    // A stored notification has to render through the same view as a live one,
    // so a title or an icon dropped here is dropped for good.
    (new DatabaseDriver(dnResolver($this->ada)))->send(
        Notification::success('Export ready')->title('Invoices')->icon('outline:check')
    );

    $row = DatabaseNotification::query()->sole();

    expect($row->type)->toBe('success')
        ->and($row->data['message'])->toBe('Export ready')
        ->and($row->data['title'])->toBe('Invoices')
        ->and($row->data['icon'])->toBe('outline:check')
        ->and($row->read_at)->toBeNull()
        ->and($row->notifiable_id)->toBe('1');
});

it('writes nothing when there is nobody to write it for', function () {
    // The ordinary state on a queue worker or in a console command. A row stored
    // against nobody cannot be read by anyone, so it would only grow a table of
    // unreachable rows.
    (new DatabaseDriver(dnResolver(null)))->send(Notification::success('Done'));

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('round-trips back into the notification it was raised as', function () {
    (new DatabaseDriver(dnResolver($this->ada)))->send(
        Notification::error('Import failed')->title('Invoices')
    );

    $notification = DatabaseNotification::query()->sole()->toNotification();

    expect($notification->type)->toBe('error')
        ->and($notification->message)->toBe('Import failed')
        ->and($notification->title)->toBe('Invoices');
});

// ─── Several drivers at once ─────────────────────────────────────────────────

it('shows the toast and keeps the record, in one send', function () {
    // The ordinary case: a user looking at another tab should still find out
    // their import finished.
    $seen = [];
    $toast = new class($seen) implements NotificationDriver
    {
        public function __construct(public array &$seen) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->seen[] = $notification->message;
        }
    };

    (new StackDriver($toast, new DatabaseDriver(dnResolver($this->ada))))
        ->send(Notification::success('Import finished'));

    expect($toast->seen)->toBe(['Import finished'])
        ->and(DatabaseNotification::query()->count())->toBe(1);
});

it('lets the rest deliver when one driver throws, then reports it', function () {
    // A notification is a courtesy: a database that is down should not also cost
    // the user their toast. But the failure still has to surface.
    $delivered = false;
    $broken = new class implements NotificationDriver
    {
        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            throw new RuntimeException('driver is down');
        }
    };
    $working = new class($delivered) implements NotificationDriver
    {
        public function __construct(public bool &$delivered) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->delivered = true;
        }
    };

    expect(fn () => (new StackDriver($broken, $working))->send(Notification::success('x')))
        ->toThrow(RuntimeException::class, 'driver is down');

    expect($delivered)->toBeTrue();
});

// ─── Reading ─────────────────────────────────────────────────────────────────

it('counts and lists only this recipient', function () {
    $forAda = new DatabaseDriver(dnResolver($this->ada));
    $forGrace = new DatabaseDriver(dnResolver($this->grace));

    $forAda->send(Notification::success('Ada 1'));
    $forAda->send(Notification::success('Ada 2'));
    $forGrace->send(Notification::success('Grace 1'));

    $center = new NotificationCenter(dnResolver($this->ada));

    expect($center->unreadCount())->toBe(2)
        ->and($center->unread()->pluck('data.message')->all())->toContain('Ada 1', 'Ada 2')
        ->and($center->unread()->pluck('data.message')->all())->not->toContain('Grace 1');
});

it('puts unread first, so a burst of reads cannot bury an old one', function () {
    $driver = new DatabaseDriver(dnResolver($this->ada));
    $driver->send(Notification::success('old unread'));
    $old = DatabaseNotification::query()->sole();
    $old->forceFill(['created_at' => now()->subDays(3)])->save();

    $driver->send(Notification::success('new read'));
    DatabaseNotification::query()->where('id', '!=', $old->id)->sole()->markAsRead();

    $center = new NotificationCenter(dnResolver($this->ada));

    expect($center->latest()->first()->data['message'])->toBe('old unread');
});

it('marks one read, and refuses an id belonging to someone else', function () {
    // The id arrives from a Livewire action, so it is user input: looking it up
    // unscoped would let one user mark another's notification read.
    (new DatabaseDriver(dnResolver($this->grace)))->send(Notification::success('Grace 1'));
    $graces = DatabaseNotification::query()->sole();

    $adasCenter = new NotificationCenter(dnResolver($this->ada));

    expect($adasCenter->markAsRead($graces->id))->toBeFalse()
        ->and($graces->fresh()->read_at)->toBeNull();

    (new DatabaseDriver(dnResolver($this->ada)))->send(Notification::success('Ada 1'));
    $adas = DatabaseNotification::query()->where('notifiable_id', '1')->sole();

    expect($adasCenter->markAsRead($adas->id))->toBeTrue()
        ->and($adas->fresh()->isRead())->toBeTrue();
});

it('marks everything read and says how many were', function () {
    $driver = new DatabaseDriver(dnResolver($this->ada));
    $driver->send(Notification::success('one'));
    $driver->send(Notification::success('two'));
    (new DatabaseDriver(dnResolver($this->grace)))->send(Notification::success('not mine'));

    $center = new NotificationCenter(dnResolver($this->ada));

    expect($center->markAllAsRead())->toBe(2)
        ->and($center->unreadCount())->toBe(0)
        // Grace's is untouched.
        ->and(DatabaseNotification::query()->whereNull('read_at')->count())->toBe(1);
});

it('does not move a timestamp that is already set', function () {
    (new DatabaseDriver(dnResolver($this->ada)))->send(Notification::success('one'));
    $row = DatabaseNotification::query()->sole();

    $row->markAsRead();
    $first = $row->fresh()->read_at;

    $row->fresh()->markAsRead();

    expect($row->fresh()->read_at->equalTo($first))->toBeTrue();
});

it('answers empty for everything when there is no recipient', function () {
    $center = new NotificationCenter(dnResolver(null));

    expect($center->unreadCount())->toBe(0)
        ->and($center->latest())->toBeEmpty()
        ->and($center->unread())->toBeEmpty()
        ->and($center->markAsRead('any'))->toBeFalse()
        ->and($center->markAllAsRead())->toBe(0);
});

it('resolves the recipient it was stored against', function () {
    // The morph relation is how an application reads a notification back to a
    // user — the same shape Laravel's own Notifiable::notifications() uses.
    (new DatabaseDriver(dnResolver($this->ada)))->send(Notification::success('one'));

    expect(DatabaseNotification::query()->sole()->notifiable()->first()?->name)->toBe('Ada');
});

it('can be marked unread again', function () {
    // The counterpart of markAsRead, and idempotent for the same reason: an
    // already-unread notification has no timestamp to clear.
    (new DatabaseDriver(dnResolver($this->ada)))->send(Notification::success('one'));

    $row = DatabaseNotification::query()->sole();
    $row->markAsRead();
    expect($row->fresh()->isRead())->toBeTrue();

    $row->fresh()->markAsUnread();
    expect(DatabaseNotification::query()->sole()->isRead())->toBeFalse();

    // Already unread: nothing to do, and nothing done.
    DatabaseNotification::query()->sole()->markAsUnread();
    expect(DatabaseNotification::query()->sole()->read_at)->toBeNull();
});

it('falls back to the authenticated user, and to nobody when there is none', function () {
    // The bound default. It answers null rather than throwing outside an auth
    // context — a worker, a console command — which is what makes the driver's
    // "write nothing" branch the ordinary path there rather than an error.
    expect((new AuthenticatedNotifiable)->resolve())->toBeNull();

    // A model that is also Authenticatable, which is what an application's User
    // is — the resolver returns it only when both hold.
    auth()->setUser(new DnAuthUser(['id' => 1, 'name' => 'Ada']));

    expect((new AuthenticatedNotifiable)->resolve()?->getKey())->toBe(1);
});

// ─── Choosing drivers from config ────────────────────────────────────────────

it('builds the driver the config names', function () {
    foreach ([
        'session' => SessionDriver::class,
        'livewire' => LivewireEventDriver::class,
        'database' => DatabaseDriver::class,
        'flasher' => FlasherDriver::class,
        'null' => NullDriver::class,
        'nonsense' => SessionDriver::class,   // an unknown name falls back, it does not fatal
    ] as $name => $expected) {
        config()->set('wire-core.notifications.default', $name);
        app()->forgetInstance(NotificationDriver::class);

        expect(app(NotificationDriver::class))->toBeInstanceOf($expected);
    }
});

it('stacks the drivers a list names, and does not stack a single one', function () {
    // The pairing the config comment recommends: toast now, record in the bell.
    config()->set('wire-core.notifications.default', ['session', 'database']);
    app()->forgetInstance(NotificationDriver::class);

    expect(app(NotificationDriver::class))->toBeInstanceOf(StackDriver::class);

    // A list of one is still just that one — no wrapper bought for nothing.
    config()->set('wire-core.notifications.default', ['database']);
    app()->forgetInstance(NotificationDriver::class);

    expect(app(NotificationDriver::class))->toBeInstanceOf(DatabaseDriver::class);

    config()->set('wire-core.notifications.default', 'session');
});

it('answers nobody when the auth guard itself cannot be resolved', function () {
    // Not a defensive flourish: a console command or a misconfigured guard has
    // no auth context at all, and the resolver has to answer rather than throw —
    // the same fail-quiet choice HasAuthorization makes.
    config()->set('auth.defaults.guard', 'no-such-guard');

    expect((new AuthenticatedNotifiable)->resolve())->toBeNull();

    config()->set('auth.defaults.guard', 'web');
});

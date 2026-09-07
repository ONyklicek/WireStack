<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Contracts\ResolvesNotifiable;
use NyonCode\WireCore\Notifications\Drivers\BroadcastDriver;
use NyonCode\WireCore\Notifications\Drivers\StackDriver;
use NyonCode\WireCore\Notifications\Events\NotificationReceived;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\Support\NotificationChannel;
use NyonCode\WireCore\WireCoreServiceProvider;

/*
 * The driver that tells a recipient's other open pages.
 *
 * The gap it closes: a queued export finishing has no component to dispatch to
 * and no session to flash into, and the row DatabaseDriver leaves behind is
 * invisible on every tab until that tab next talks to the server.
 */
class BdUser extends Model
{
    protected $table = 'bd_users';

    protected $guarded = [];

    public $timestamps = false;
}

/**
 * Re-run the provider's channel registration against the current config.
 *
 * The real call site is `bootNotifications()`, which has already run by the time
 * a test can set config — so the method is invoked directly rather than the whole
 * application rebooted per case.
 */
function bootChannelAuthorization(): void
{
    $provider = app()->getProvider(WireCoreServiceProvider::class);

    (fn () => $this->authorizeNotificationChannel())->call($provider);
}

/** @param Model|null $user who the application says the recipient is */
function bdDriver(?Model $user): BroadcastDriver
{
    return new BroadcastDriver(new class($user) implements ResolvesNotifiable
    {
        public function __construct(private ?Model $user) {}

        public function resolve(): ?Model
        {
            return $this->user;
        }
    });
}

it('announces the arrival on the recipient own channel', function () {
    Event::fake();

    bdDriver(new BdUser(['id' => 5]))->send(Notification::success('Export ready'));

    Event::assertDispatched(
        NotificationReceived::class,
        fn (NotificationReceived $e): bool => $e->channel === 'wire-notifications.BdUser.5',
    );
});

it('announces nothing when there is nobody to tell', function () {
    // The ordinary state on a queue worker or in a console command. A channel
    // named after nobody is one no client is subscribed to, so the honest
    // answer is silence rather than a broadcast into the dark.
    Event::fake();

    bdDriver(null)->send(Notification::success('Export ready'));

    Event::assertNotDispatched(NotificationReceived::class);
});

it('carries the notification nowhere near the wire', function () {
    Event::fake();

    bdDriver(new BdUser(['id' => 5]))->send(
        Notification::error('The salary export failed')->title('Payroll'),
    );

    Event::assertDispatched(function (NotificationReceived $e): bool {
        $serialised = json_encode([$e->channel, $e->broadcastWith()]);

        // Not a style preference: whatever reaches broadcastWith() is readable
        // by anything that gets onto the socket, and the recipient scoping would
        // move from a server render to a channel subscription.
        return ! str_contains((string) $serialised, 'salary')
            && ! str_contains((string) $serialised, 'Payroll');
    });
});

it('is what the config name resolves to, alongside the rest of a stack', function () {
    config()->set('wire-core.notifications.default', ['session', 'database', 'broadcast']);

    $driver = app(NotificationDriver::class);

    expect($driver)->toBeInstanceOf(StackDriver::class);

    // The arrangement the docs recommend: the toast for the tab that asked, the
    // row for later, and the nudge for every other tab.
    $reflected = new ReflectionProperty(StackDriver::class, 'drivers');

    expect(array_map(fn (object $d): string => $d::class, $reflected->getValue($driver)))
        ->toContain(BroadcastDriver::class);
});

it('authorizes the channel for an app that broadcasts and said nothing further', function () {
    config()->set('wire-core.notifications.default', ['database', 'broadcast']);

    bootChannelAuthorization();

    expect(Broadcast::getFacadeRoot()->driver()->getChannels())
        ->toHaveKey(NotificationChannel::PATTERN);
});

it('never resolves a broadcaster for an application that broadcasts nothing', function () {
    // The gate is not a nicety. `Broadcast::channel()` resolves the default
    // connection, so registering this unconditionally would construct a
    // broadcaster in every wire-core application — including the ones with no
    // credentials for the driver their config happens to name.
    config()->set('wire-core.notifications.default', ['session', 'database']);

    bootChannelAuthorization();

    expect(Broadcast::getFacadeRoot()->driver()->getChannels())
        ->not->toHaveKey(NotificationChannel::PATTERN);
});

it('leaves the channel to the application when asked to', function () {
    // An app writing its own rule in routes/channels.php — a supervisor watching
    // a queue, a tenancy rule — must not have ours registered underneath it.
    config()->set('wire-core.notifications.default', 'broadcast');
    config()->set('wire-core.notifications.broadcast.authorize', false);

    bootChannelAuthorization();

    expect(Broadcast::getFacadeRoot()->driver()->getChannels())
        ->not->toHaveKey(NotificationChannel::PATTERN);
});

it('resolves the recipient through the binding, not through its own default', function () {
    // One answer to "who is this for", shared by the driver that writes the row,
    // the driver that names the channel, and the bell that subscribes to it. A
    // driver keeping its own would broadcast to a channel nobody is on.
    $tenant = new BdUser(['id' => 42]);

    app()->bind(ResolvesNotifiable::class, fn () => new class($tenant) implements ResolvesNotifiable
    {
        public function __construct(private Model $user) {}

        public function resolve(): ?Model
        {
            return $this->user;
        }
    });

    config()->set('wire-core.notifications.default', 'broadcast');
    Event::fake();

    app(NotificationDriver::class)->send(Notification::info('hello'));

    Event::assertDispatched(
        NotificationReceived::class,
        fn (NotificationReceived $e): bool => $e->channel === 'wire-notifications.BdUser.42',
    );
});

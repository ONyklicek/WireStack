<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Notifications\Channels\WireChannel;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\DatabaseNotification;
use NyonCode\WireCore\Notifications\Notification;

/*
 * Laravel's notification system delivering into this one.
 *
 * Small surface, large gain, and the gain is the point of the tests: ShouldQueue,
 * Notifiable, via() deciding per user and mail going out beside the bell entry
 * are all Laravel's, and all of it reaches the bell through one channel class.
 * What has to be true is that the recipient survives the trip — a queued job has
 * nobody logged in.
 */
class WcUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'wc_users';

    protected $guarded = [];

    public $timestamps = false;
}

class WcInvoicePaid extends LaravelNotification
{
    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return [WireChannel::NAME];
    }

    public function toWire(mixed $notifiable): Notification
    {
        return Notification::success('Invoice paid')->title('INV-42')->url('/invoices/42');
    }
}

/** A notification that names a different recipient than the one notified. */
class WcEscalation extends LaravelNotification
{
    public function __construct(private Model $manager) {}

    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return [WireChannel::NAME];
    }

    public function toWire(mixed $notifiable): Notification
    {
        return Notification::warning('Escalated')->to($this->manager);
    }
}

class WcSilent extends LaravelNotification
{
    /** @return array<int, string> */
    public function via(mixed $notifiable): array
    {
        return [WireChannel::NAME];
    }
}

beforeEach(function () {
    Schema::create('wc_users', function (Blueprint $t) {
        $t->id();
        $t->string('email')->nullable();
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

    config()->set('wire-core.notifications.default', 'database');

    $this->ada = WcUser::create(['id' => 1]);
    $this->grace = WcUser::create(['id' => 2]);
});

afterEach(function () {
    Schema::dropIfExists('wire_notifications');
    Schema::dropIfExists('wc_users');
});

it('is registered under the name an application writes in via()', function () {
    expect(app(ChannelManager::class)->driver(WireChannel::NAME))->toBeInstanceOf(WireChannel::class);
});

it('delivers $user->notify() into the bell, addressed to that user', function () {
    // Nobody is signed in — the queue-worker case, and the reason this bridge is
    // worth having at all.
    $this->ada->notify(new WcInvoicePaid);

    $row = DatabaseNotification::query()->sole();

    expect($row->notifiable_id)->toBe('1')
        ->and($row->notifiable_type)->toBe(WcUser::class)
        ->and($row->data['title'])->toBe('INV-42')
        // The payload arrives whole, so a stored notification renders the same
        // as a live one.
        ->and($row->data['url'])->toBe('/invoices/42');
});

it('lets a notification address somebody other than the model notified', function () {
    // An escalation to a manager, a copy to an inbox. The channel fills in the
    // recipient only when the notification did not say.
    $this->ada->notify(new WcEscalation($this->grace));

    expect(DatabaseNotification::query()->sole()->notifiable_id)->toBe('2');
});

it('delivers nothing, rather than fatally, for a notification that cannot render one', function () {
    // `via()` listing a channel the notification has no method for is the
    // application's mistake — and taking down a queued job that also had mail to
    // send is not the way to tell them about it.
    $this->ada->notify(new WcSilent);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('ignores an anonymous notifiable rather than storing a row nobody owns', function () {
    // Notification::route() is a real thing to do and has no row to be stored
    // against; the driver's own fail-quiet answer covers it.
    (new WireChannel(app(NotificationDriver::class)))->send(new stdClass, new WcInvoicePaid);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;
use NyonCode\WireCore\Notifications\Events\NotificationReceived;
use NyonCode\WireCore\Notifications\Support\NotificationChannel;

/*
 * The channel a recipient's notifications arrive on.
 *
 * Everything here guards the same failure, and it is the quiet kind: a name that
 * does not match on both ends raises nothing. The subscription is refused, the
 * push stops arriving, the bell still updates on every render — and nobody
 * learns the live half is dead. So the name, the authorization and the event's
 * view of the name are asserted together.
 */
class NcUser extends Model
{
    protected $table = 'nc_users';

    protected $guarded = [];

    public $timestamps = false;
}

class NcAliasUser extends NcUser
{
    public function getMorphClass(): string
    {
        // A morph map alias, and one with a hyphen in it: the case that makes
        // decoding the segment back into a class name impossible to do safely.
        return 'blog-author';
    }
}

it('names one channel per recipient, dot-free so a wildcard can match it', function () {
    $user = new NcUser(['id' => 7]);

    expect(NotificationChannel::for($user))->toBe('wire-notifications.NcUser.7');
});

it('encodes a namespaced morph class without dots or backslashes', function () {
    // Laravel compiles a {placeholder} to ([^\.]+), so a dotted class could not
    // be matched by the wildcard at all; a backslash is not a legal Pusher
    // channel character either.
    $user = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'App\\Models\\User';
        }

        public function getKey(): mixed
        {
            return 3;
        }
    };

    expect(NotificationChannel::for($user))->toBe('wire-notifications.App-Models-User.3');
});

it('lets the viewer onto their own channel and nobody else onto it', function () {
    $ada = new NcUser(['id' => 1]);
    $grace = new NcUser(['id' => 2]);

    expect(NotificationChannel::matches($ada, 'NcUser', '1'))->toBeTrue()
        ->and(NotificationChannel::matches($grace, 'NcUser', '1'))->toBeFalse()
        ->and(NotificationChannel::matches($ada, 'OtherModel', '1'))->toBeFalse()
        // Signed out is not a recipient, and a channel is not a public place.
        ->and(NotificationChannel::matches(null, 'NcUser', '1'))->toBeFalse();
});

it('compares morph aliases as they were encoded, never decoded back', function () {
    // `blog-author` would decode to `blog\author`, which is nothing. Comparing in
    // the encoded space is exact, so an alias with a hyphen still authorizes.
    $author = new NcAliasUser(['id' => 9]);

    expect(NotificationChannel::for($author))->toBe('wire-notifications.blog-author.9')
        ->and(NotificationChannel::matches($author, 'blog-author', '9'))->toBeTrue();
});

it('registers one wildcard callback rather than a line per recipient', function () {
    NotificationChannel::authorize();

    // Registered on the broadcaster under the pattern, which is what an app
    // would otherwise have had to write in routes/channels.php.
    expect(Broadcast::getFacadeRoot()->driver()->getChannels())
        ->toHaveKey(NotificationChannel::PATTERN);
});

it('takes the application own rule instead when it has one', function () {
    NotificationChannel::authorize(fn ($user, string $notifiable, string $key): bool => true);

    $channels = Broadcast::getFacadeRoot()->driver()->getChannels();
    $callback = $channels[NotificationChannel::PATTERN];

    expect($callback(new NcUser(['id' => 1]), 'NcUser', '999'))->toBeTrue();
});

it('broadcasts on that channel and nothing else, carrying no payload', function () {
    $event = NotificationReceived::for(new NcUser(['id' => 4]));

    expect($event->broadcastOn())->toBe(['private-wire-notifications.NcUser.4'])
        ->and($event->broadcastAs())->toBe('wire-notification.received')
        // The whole design: a nudge to re-read, never the text of the
        // notification. A payload here would be readable by anything that got
        // onto the socket, and would move the recipient scoping from a server
        // render to a channel subscription.
        ->and($event->broadcastWith())->toBe([]);
});

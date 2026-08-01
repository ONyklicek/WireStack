<?php

declare(strict_types=1);

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use NyonCode\WireTable\Events\TableRecordsChanged;
use NyonCode\WireTable\WireTableServiceProvider;

/*
 * `live(broadcast: true)` must never care which broadcaster the app uses.
 *
 * Pusher, Ably, Reverb, a self-hosted bridge — all of them are the application's
 * choice and none is a dependency of this package. That is easy to state and easy
 * to lose: one `window.Echo.connector.pusher.…` reached for while debugging, or
 * one broadcaster package added to a composer.json to make a test pass, and the
 * promise is quietly gone. Nothing else would fail — the repository verifies
 * against Reverb, so Reverb-shaped coupling would keep every other check green.
 *
 * These are cheap guards over exactly that.
 */
$src = fn (string $path): string => file_get_contents(
    dirname(WireTableServiceProvider::ASSETS_PATH).'/'.$path
);

it('is a plain Laravel broadcast event, with no broadcaster in sight', function () {
    $event = new TableRecordsChanged('App\\Models\\Invoice');

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class)
        // String channel names rather than Channel objects: the same thing on the
        // wire, and it keeps illuminate/broadcasting out of this package's
        // requirements as well.
        ->and($event->broadcastOn())->each->toBeString()
        ->and($event->broadcastAs())->toBeString();
});

it('names no broadcaster anywhere in the shipped client bridge', function () use ($src) {
    $bridge = $src('resources/js/record-live.js');

    // The Echo facade is the entire client contract. Reaching past it — at
    // `connector`, at a `pusher`/`ably` object, at `window.Pusher` — is what ties
    // the bridge to one vendor.
    expect($bridge)->toContain('window.Echo.private(')
        ->and($bridge)->toContain('window.Echo.leave(');

    foreach (['connector', 'window.Pusher', 'Ably', 'io(', 'socket.io'] as $forbidden) {
        expect(str_contains($bridge, $forbidden))
            ->toBeFalse("record-live.js reaches past window.Echo via [{$forbidden}]");
    }
});

it('requires no broadcaster package', function () {
    // A broadcaster in any package's composer.json would make the whole feature
    // mandatory rather than opt-in, and would pick the vendor for the consumer.
    //
    // The monorepo's OWN composer.json is deliberately not checked: it carries
    // laravel/reverb in require-dev to run verify-live-broadcast-real.mjs, which
    // is the test rig and ships to nobody. Widening this loop to the repository
    // root would fail on exactly that, and "fixing" it by dropping the dev
    // dependency would take the only real-transport verification with it.
    foreach (glob(dirname(__DIR__, 3).'/*/composer.json') as $manifest) {
        $deps = json_decode((string) file_get_contents($manifest), true);

        foreach (['require', 'require-dev'] as $section) {
            foreach (array_keys($deps[$section] ?? []) as $package) {
                expect($package)
                    ->not->toContain('reverb')
                    ->not->toContain('pusher')
                    ->not->toContain('ably');
            }
        }
    }
});

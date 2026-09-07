<?php

declare(strict_types=1);

use NyonCode\WireCore\WireCoreServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/*
 * The bell's live bridge, from source to <script>.
 *
 * The one failure worth guarding: this bundle is delivered late by definition —
 * the bell arrives with whatever page a `wire:navigate` lands on — so a
 * registrar that only listened for `alpine:init` would register nothing, and
 * `x-data="wireNotificationLive(…)"` would take the whole bell down against an
 * empty registry. Silent in PHP tests, which is why the shape is asserted here
 * and the behaviour in the browser drivers.
 */

test('the live bridge is shipped inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-notifications.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireNotificationLive');
});

test('the package serves it without publishing or a build step', function () {
    $response = $this->get('/wire-core/assets/notifications.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
});

test('the source registers unconditionally so the bundle survives a wire:navigate', function () {
    $source = file_get_contents(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/notification-live.js'
    );

    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) registerWireNotificationLive\(\)/")
        ->toMatch("/document\.addEventListener\('alpine:init', registerWireNotificationLive\)/")
        ->toContain('if (registered || ! window.Alpine) return')
        ->toContain("window.Alpine.data('wireNotificationLive', wireNotificationLive)");
});

test('the shipped bundle carries the SPA-proof registration', function () {
    // Minified shape of the idiom above. Fails if the dist drifts from the
    // source because the asset was not recompiled (`npm run build:core-assets`).
    expect(file_get_contents(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-notifications.js'))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toContain('wireNotificationLive');
});

test('it speaks to Echo and to nothing else', function () {
    // Which broadcaster carries this is the application's business. One
    // `.connector.pusher` reached for while debugging and the promise is gone —
    // and nothing else would fail, because the repository verifies against
    // Reverb.
    $source = file_get_contents(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/notification-live.js'
    );

    expect($source)->toContain('window.Echo.private(')
        ->and($source)->toContain('window.Echo.leave(');

    $forbidden = [
        '/\.connector\b/' => 'reaches through Echo.connector',
        '/\bwindow\.Pusher\b/' => 'touches the Pusher global',
        '/\bnew\s+(Pusher|Ably)\b/' => 'constructs a broadcaster client',
        '/\bfrom\s+[\'"](pusher|ably|socket\.io)/' => 'imports a broadcaster client',
    ];

    foreach ($forbidden as $pattern => $what) {
        expect(preg_match($pattern, $source))
            ->toBe(0, "notification-live.js {$what} instead of speaking only to window.Echo");
    }
});

test('it degrades to a bell that is merely late, never to one that throws', function () {
    $source = file_get_contents(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/notification-live.js'
    );

    // No Echo on the page at all is the ordinary case, not an error: the bell is
    // still right on every render.
    expect($source)->toContain('if (! this.channel || ! window.Echo) return')
        // A refused subscription is the one failure that looks like success, so
        // it is the one that says something — once.
        ->toContain('console.warn')
        ->toContain('_reportedRefusal');
});

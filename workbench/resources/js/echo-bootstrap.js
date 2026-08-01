/*
 * A real Laravel Echo on the preview page.
 *
 * The stubbed Echo in verify-live-broadcast.mjs proves the bridge: the table
 * subscribes, an event makes it re-read, a save in flight holds the re-read off.
 * It cannot prove the parts that live outside this repo — that the event
 * serialises, that `broadcastOn()` names a channel the server accepts, that
 * `broadcastAs()` is what the client ends up listening for, and that
 * private-channel authorization lets the subscription through. This is the real
 * client, talking to a real Reverb, so a driver can watch all of that happen.
 *
 * Workbench-only, and deliberately not part of any shipped bundle: Echo is the
 * application's dependency, never the package's.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const cfg = window.__wireEchoConfig ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: cfg.key,
    wsHost: cfg.host,
    wsPort: cfg.port,
    wssPort: cfg.port,
    forceTLS: false,
    enabledTransports: ['ws'],
    disableStats: true,
});

// A driver needs to know the socket is actually up before it asserts on
// anything — subscribing before the connection is established would look like a
// missing broadcast rather than a race.
window.__echoReady = new Promise((resolve) => {
    const c = window.Echo.connector.pusher.connection;

    if (c.state === 'connected') {
        resolve(c.socket_id);

        return;
    }

    c.bind('connected', () => resolve(c.socket_id));
});

window.__echoState = () => window.Echo.connector.pusher.connection.state;

<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\ApplicationManagerServiceProvider;
use Laravel\Reverb\ReverbServiceProvider;
use NyonCode\WireTable\Support\LiveChannel;
use Workbench\App\Models\User;

/**
 * A real broadcast transport for the workbench, so `Table::live(broadcast: true)`
 * can be verified over an actual WebSocket rather than a stubbed `window.Echo`.
 *
 * The stub proves the bridge — that the table subscribes to the right channel and
 * that an event on it makes the table re-read. It cannot prove the half that
 * belongs to Laravel: that `TableRecordsChanged` is serialisable, that its
 * `broadcastOn()` string is a channel Reverb accepts, that `broadcastAs()` is the
 * name the client ends up listening for, and that private-channel authorization
 * lets the subscription through. Those only fail against a real server, which is
 * what this wires up.
 *
 * Workbench-only, and self-installing: no broadcaster is a dependency of this
 * repository, so nothing here may be referenced from testbench.yaml or from a
 * `use` statement that would fatal when the package is absent. The Reverb
 * providers are registered from in here, guarded by class_exists, and everything
 * else short-circuits on {@see enabled()}.
 *
 * To run it (all of it opt-in, none of it in CI):
 *
 *   composer require --dev laravel/reverb
 *   npm i -D laravel-echo pusher-js && npm run build:workbench-echo
 *   touch workbench/storage/wire-broadcast.enabled
 *   vendor/bin/testbench reverb:start --host=127.0.0.1 --port=8090 &
 *   vendor/bin/testbench serve --host=127.0.0.1 --port=8085 &
 *   node workbench/scripts/verify-live-broadcast-real.mjs
 *
 * Everything here is configuration an application would own, and the package
 * requires none of it — a table with `live()` and no broadcasting at all still
 * refreshes on its interval.
 */
class WorkbenchBroadcastServiceProvider extends ServiceProvider
{
    /** Credentials shared with the Reverb server the driver starts. */
    public const APP_ID = 'wire-workbench';

    public const APP_KEY = 'wire-workbench-key';

    public const APP_SECRET = 'wire-workbench-secret';

    public const PORT = 8090;

    /**
     * The switch is a FILE, not an environment variable.
     *
     * `testbench serve` runs the built-in PHP server as a subprocess that does
     * not inherit the shell's environment, so `WIRE_BROADCAST=1 testbench serve`
     * reads as set in the console and as unset in every request the server then
     * handles — the provider stays inert, `/broadcasting/auth` 404s, and the page
     * ships no Echo, all without a single error to explain it. A file is visible
     * to whichever process asks.
     */
    public static function flagPath(): string
    {
        return dirname(__DIR__, 2).'/storage/wire-broadcast.enabled';
    }

    /**
     * On only when the flag is set AND the pieces it needs are actually installed.
     *
     * Both halves matter. A flag left behind after `composer remove laravel/reverb`
     * would otherwise put a `<script>` for a bundle that is not there into every
     * preview, and point the app's broadcasting at a driver that no longer exists
     * — so every write would fail on something unrelated to what it was doing.
     */
    public static function enabled(): bool
    {
        return is_file(self::flagPath())
            && class_exists(ReverbServiceProvider::class)
            && is_file(self::echoBundlePath());
    }

    /** The bundled Echo client the preview layout serves; built by `npm run build:workbench-echo`. */
    public static function echoBundlePath(): string
    {
        return dirname(__DIR__, 2).'/resources/dist/echo-bootstrap.js';
    }

    public function register(): void
    {
        // Only when asked for: the default workbench has no broadcast server
        // running, and pointing at one that is not there makes every write pay a
        // failed HTTP call to it.
        if (! self::enabled()) {
            return;
        }

        // Registered here rather than in testbench.yaml, which cannot express
        // "only if installed": the deferred ApplicationManager provider first,
        // because the server command resolves ApplicationProvider out of it.
        $this->app->register(ApplicationManagerServiceProvider::class);
        $this->app->register(ReverbServiceProvider::class);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => self::APP_KEY,
                'secret' => self::APP_SECRET,
                'app_id' => self::APP_ID,
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => self::PORT,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
                // The write that broadcasts is the one the user is waiting on, and
                // a queued broadcast would leave the driver waiting on a worker
                // that is not running. Sync keeps the whole path inside the
                // request, which is also the stricter test.
                'client_options' => [],
            ],
            'reverb.default' => 'reverb',
            'reverb.servers.reverb.host' => '127.0.0.1',
            'reverb.servers.reverb.port' => self::PORT,
            'reverb.servers.reverb.hostname' => '127.0.0.1',
            'reverb.apps.provider' => 'config',
            'reverb.apps.apps' => [[
                'key' => self::APP_KEY,
                'secret' => self::APP_SECRET,
                'app_id' => self::APP_ID,
                'options' => [
                    'host' => '127.0.0.1',
                    'port' => self::PORT,
                    'scheme' => 'http',
                    'useTLS' => false,
                ],
                'allowed_origins' => ['*'],
                'ping_interval' => 60,
                'activity_timeout' => 30,
                'max_message_size' => 10_000,
            ]],
        ]);
    }

    public function boot(): void
    {
        if (! self::enabled()) {
            return;
        }

        Broadcast::routes();

        // The channel the table names after its model. Authorizing it is the
        // application's job — this is the workbench being that application, and
        // it is deliberately a real callback rather than a blanket allow, so the
        // driver exercises the same `/broadcasting/auth` round trip a real app does.
        LiveChannel::authorize(
            fn (User $user, string $model): bool => $model === User::class,
        );

        // A private channel needs somebody to authorize. Previews have no login
        // screen, so the first seeded user stands in for one.
        if (Auth::guest()) {
            $user = User::query()->orderBy('id')->first();

            if ($user !== null) {
                Auth::login($user);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Install;

use Illuminate\Support\Facades\Cache;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\EnvFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use Throwable;

/**
 * Somewhere to cache settings that is not the table they came from.
 *
 * The installer already said it — "Settings cache in the `database` store —
 * name a memory store in wire-module-settings.cache.store" — and the reason it
 * matters is circular: settings are read on nearly every request, and caching
 * them in the database means the cache lookup costs the query it was meant to
 * save. It works. It is simply the one configuration where the cache is not one.
 *
 * Only ever offered where there is somewhere better to go: an application whose
 * only store is the database is answered by leaving it alone, because the fix
 * there is installing Redis and not editing a key.
 */
final readonly class CacheSettingsInMemory implements SetupStep
{
    private const KEY = 'WIRE_SETTINGS_CACHE_STORE';

    /** Stores that are actually memory, best first. */
    private const PREFERRED = ['redis', 'memcached', 'octane', 'apc'];

    public function __construct(private EnvFile $env) {}

    public function label(): string
    {
        return 'Settings cache';
    }

    public function state(): SetupState
    {
        if (! $this->onTheDatabase()) {
            return SetupState::Done;
        }

        if ($this->candidates() === []) {
            // Nothing better is configured, so there is nothing to offer. Not
            // Blocked either: this is a working installation, just not a fast
            // one, and the answer is a server rather than a setting.
            return SetupState::Done;
        }

        return $this->env->exists() ? SetupState::Pending : SetupState::Blocked;
    }

    public function summary(): string
    {
        if (! $this->onTheDatabase()) {
            return 'cached in `'.$this->store().'`';
        }

        if ($this->candidates() === []) {
            return 'cached in the database, with nowhere better configured';
        }

        return $this->env->exists()
            ? 'move the cache off the database'
            : 'cached in the database and there is no .env to change it in';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $candidates = $this->candidates();

        $store = $console->choose(
            'Which store should settings be cached in?',
            array_combine($candidates, $candidates),
            $candidates[0],
        );

        if (! $this->env->set(self::KEY, $store)) {
            $console->warn('Could not write .env — set '.self::KEY.'='.$store.' yourself.');

            return SetupOutcome::Failed;
        }

        $console->note("Settings are cached in `{$store}`.");

        return SetupOutcome::Applied;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-settings';
    }

    public function sort(): int
    {
        return 800;
    }

    private function store(): string
    {
        return (string) (config('wire-module-settings.cache.store') ?? config('cache.default', 'file'));
    }

    private function onTheDatabase(): bool
    {
        return $this->store() === 'database';
    }

    /**
     * The memory stores this application has configured, and that answer.
     *
     * Read off `cache.stores` rather than offered from a list, because naming
     * `redis` in an application with no redis connection swaps a slow cache for
     * a broken one. And configured is not enough: a fresh Laravel application
     * lists `redis`, `memcached` and `octane` in its `config/cache.php` whether
     * or not there is a server, an extension or Octane behind them. Offered on
     * the strength of the config alone, the first run in a real application
     * wrote `redis` as the default into `.env` of a PHP with no Redis class, and
     * every settings read after it threw. So each store is asked for one key,
     * which is looking rather than changing, and one that throws is not offered.
     *
     * @return array<int, string>
     */
    private function candidates(): array
    {
        /** @var array<string, array<string, mixed>> $stores */
        $stores = (array) config('cache.stores', []);

        return array_values(array_filter(
            self::PREFERRED,
            static fn (string $name): bool => array_key_exists($name, $stores) && self::answers($name),
        ));
    }

    private static function answers(string $store): bool
    {
        try {
            Cache::store($store)->get('wire-module-settings:probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

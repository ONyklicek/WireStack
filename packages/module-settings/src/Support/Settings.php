<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use NyonCode\WireModuleSettings\Events\SettingsSaved;
use NyonCode\WireModuleSettings\Models\Setting;

/**
 * Read and write application settings.
 *
 * A settings value is read on nearly every request and written almost never, so
 * the whole group is cached as one entry and the cache is dropped on write. Per
 * key would be one lookup per read; per group is one for a page that reads six.
 *
 * **Reads never touch the database when the table is not there.** A settings
 * call sits in application code that also runs during `migrate` and in a fresh
 * install, and a missing table must answer the default rather than throw — which
 * is what makes `Settings::get()` safe to put in a config-like place.
 *
 * **A missing table is never cached, though**, and that is the difference
 * between the sentence above being true and being true once. `rememberForever`
 * stores whatever the callback returned, so the empty array a pre-migration read
 * produced was written into the cache and answered every read afterwards — a
 * fresh install whose deploy touched a setting before `migrate` came up with
 * every value empty and stayed that way until something wrote one. The table
 * check now short-circuits *before* the store is written to.
 *
 * **A declared default is a value.** {@see ProvidesSettingsDefaults} lets a
 * group say what its values are before anybody sets them, and those come back
 * from `get()` and `all()` under whatever is stored — so the default lives in
 * the group rather than being repeated at every call site, where five of the six
 * copies agree.
 */
final class Settings
{
    private const CACHE_PREFIX = 'wire-settings.';

    /**
     * One value: stored, else declared by the group, else what the caller named.
     */
    public static function get(string $key, mixed $default = null, string $group = 'general'): mixed
    {
        $values = self::all($group);

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /** Whether anything is stored under this key — a declared default is not. */
    public static function has(string $key, string $group = 'general'): bool
    {
        return array_key_exists($key, self::stored($group));
    }

    /**
     * Every value in a group, keyed: the group's declared defaults with whatever
     * is stored written over them.
     *
     * @return array<string, mixed>
     */
    public static function all(string $group = 'general'): array
    {
        return [...SettingsGroups::defaultsFor($group), ...self::stored($group)];
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        self::fill([$key => $value], $group);
    }

    /**
     * Write a whole group at once — what a settings form does on save.
     *
     * **One transaction**, which is the promise the settings screen is built on:
     * it edits one group at a time so it never has to say "your branding saved
     * but your mail did not", and a loop of six writes where the fourth fails
     * says exactly that about one group's own fields. All of it lands or none
     * of it does.
     *
     * The cache is dropped after the transaction rather than inside it. The
     * model drops it too, on every write (see {@see Setting::booted()}) — that
     * is the guard for a value changed from a seeder or from tinker — but a
     * forget that happens mid-transaction can be followed by a read that caches
     * the pre-commit rows again, so the one that matters is this one.
     *
     * @param  array<string, mixed>  $values
     */
    public static function fill(array $values, string $group = 'general'): void
    {
        if ($values === []) {
            return;
        }

        Setting::query()->getConnection()->transaction(static function () use ($values, $group): void {
            foreach ($values as $key => $value) {
                Setting::query()->updateOrCreate(
                    ['group' => $group, 'key' => (string) $key],
                    ['value' => $value],
                );
            }
        });

        self::forget($group);

        event(new SettingsSaved($group, $values));
    }

    /** Remove one stored value, so the group's declared default answers again. */
    public static function remove(string $key, string $group = 'general'): void
    {
        Setting::query()->where('group', $group)->where('key', $key)->delete();

        self::forget($group);
    }

    /** Remove everything stored for a group. */
    public static function clear(string $group = 'general'): void
    {
        Setting::query()->where('group', $group)->delete();

        self::forget($group);
    }

    /** Drop a group's cached values, leaving what is stored alone. */
    public static function forget(string $group = 'general'): void
    {
        self::cache()->forget(self::CACHE_PREFIX.$group);
    }

    /**
     * What is actually in the table for a group, cached.
     *
     * Separate from {@see all()} so the cache holds stored rows and nothing else:
     * a declared default merged in here would be frozen into the store, and
     * changing it in the group class would then take a cache clear to be seen.
     *
     * @return array<string, mixed>
     */
    private static function stored(string $group): array
    {
        if (! self::caching()) {
            return self::tableExists() ? self::read($group) : [];
        }

        $cache = self::cache();
        $cached = $cache->get(self::CACHE_PREFIX.$group);

        if (is_array($cached)) {
            return $cached;
        }

        // Answered, and deliberately not written to the store: a read before
        // `migrate` must not be the answer this application gives afterwards.
        if (! self::tableExists()) {
            return [];
        }

        $values = self::read($group);

        $cache->forever(self::CACHE_PREFIX.$group, $values);

        return $values;
    }

    /** @return array<string, mixed> */
    private static function read(string $group): array
    {
        return Setting::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * The store settings are cached in.
     *
     * Configurable because the default store is the wrong one often enough to
     * matter: settings are read on nearly every request, and a `database` store
     * turns the one query this class exists to avoid into a different one. An
     * application on Redis names it here and the read stops touching the
     * database at all.
     */
    private static function cache(): Repository
    {
        $store = config('wire-module-settings.cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    private static function caching(): bool
    {
        return (bool) config('wire-module-settings.cache.enabled', true);
    }

    /**
     * Whether the table is there at all.
     *
     * Called only on a cache miss, so it costs one schema query per group per
     * cache lifetime rather than one per read. Deliberately **not** memoised in
     * a static: under Octane a static outlives the request, so an application
     * that migrated after its first read would keep answering "no table" until
     * the worker was restarted.
     */
    private static function tableExists(): bool
    {
        return Setting::query()->getConnection()
            ->getSchemaBuilder()
            ->hasTable((new Setting)->getTable());
    }
}

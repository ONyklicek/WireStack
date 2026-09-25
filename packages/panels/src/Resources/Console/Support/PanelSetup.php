<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Console\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * What stands between a class a generator just wrote and a page a person can
 * open — read off the application as it is, so a generator can say "done, it is
 * at /admin/orders" or name exactly the step that is missing.
 *
 * Two questions, each answered from where the answer actually lives:
 *
 * - **Is it registered?** Listed in the config list for its kind, or inside a
 *   folder `config('wire-core.discover')` names for that kind.
 * - **Is anything routing it?** `config('wire-panels.routes.enabled')`, or a
 *   route file that calls `Route::wireResources()` / `Route::wireResource()`.
 *   Read from the files rather than the router because a command runs before
 *   any request, and an application may register its routes only for HTTP.
 */
final class PanelSetup
{
    public function __construct(private readonly Filesystem $files) {}

    /**
     * @param  string  $kind  `resources` or `pages` — the key under `wire-core.discover`.
     * @param  array<int, mixed>  $listed  The config list for the kind.
     */
    public function isRegistered(string $class, string $kind, array $listed): bool
    {
        $class = ltrim($class, '\\');

        foreach ($listed as $entry) {
            if (is_string($entry) && ltrim($entry, '\\') === $class) {
                return true;
            }
        }

        return $this->isDiscovered($class, $kind);
    }

    /** Whether a folder `config('wire-core.discover.{kind}')` names holds the class. */
    public function isDiscovered(string $class, string $kind): bool
    {
        $folders = config("wire-core.discover.{$kind}", []);

        foreach (is_array($folders) ? $folders : [] as $namespace => $directory) {
            if (is_string($namespace) && str_starts_with(ltrim($class, '\\'), trim($namespace, '\\').'\\')) {
                return true;
            }
        }

        return false;
    }

    /** Whether the application routes registered classes at all. */
    public function isRouted(): bool
    {
        return config('wire-panels.routes.enabled') === true || $this->routeFileCallsTheMacro();
    }

    /**
     * The path a key's list lands at, as far as it can be known here: the
     * configured prefix when routes come from config, or the bare segment under
     * whatever group a route file puts `Route::wireResources()` in.
     */
    public function pathFor(string $key): string
    {
        $prefix = config('wire-panels.routes.enabled') === true ? trim((string) config('wire-panels.routes.prefix'), '/') : '';

        return '/'.ltrim(($prefix === '' ? '' : $prefix.'/').$key, '/');
    }

    /** Whether the prefix in {@see pathFor()} is the whole path, or a route group may add to it. */
    public function knowsTheWholePath(): bool
    {
        return config('wire-panels.routes.enabled') === true;
    }

    /** The key a resource over this model is registered under — `DescribesRecords`' rule. */
    public function resourceKey(string $model): string
    {
        return Str::of(class_basename($model))->kebab()->plural()->value();
    }

    /** Whether a route file — `routes/*.php` — calls `Route::wireResources()` or `Route::wireResource()`. */
    private function routeFileCallsTheMacro(): bool
    {
        foreach (glob(base_path('routes/*.php')) ?: [] as $file) {
            if (preg_match('/\bwireResources?\s*\(/', $this->files->get($file)) === 1) {
                return true;
            }
        }

        return false;
    }
}

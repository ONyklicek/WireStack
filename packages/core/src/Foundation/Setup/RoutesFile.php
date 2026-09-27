<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

/**
 * The application's `routes/web.php`, as something a setup step can add one
 * `Route::wire('…')` call to.
 *
 * Every package's routes are a group the application places (ADR 0041): with
 * `Route::wire('key')` in its route file, inside the prefix and middleware it
 * chose, or with an entry of `wire-core.routes.groups`. An installer writes the
 * first, once, and never over anything — and leaves both alone when either is
 * already there. This is the one place that decides "already written" and the
 * one that appends.
 */
final readonly class RoutesFile
{
    public function __construct(private string $path) {}

    public static function forApplication(): self
    {
        return new self(base_path('routes/web.php'));
    }

    /** Whether there is a route file to write to. */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Whether the file already calls this macro, anywhere in it.
     *
     * Read as text, not parsed: a call the application moved into a group, a
     * closure or another line is still the application's decision, and a step
     * that looked for its own exact snippet would append a second one beside it.
     */
    public function calls(string $macro): bool
    {
        return $this->exists() && str_contains((string) file_get_contents($this->path), $macro.'(');
    }

    /**
     * Whether the file places this route group — `Route::wire('panel')`, with
     * whatever arguments or group around it.
     */
    public function wires(string $group): bool
    {
        return $this->exists()
            && preg_match('/\bwire\(\s*[\'"]'.preg_quote($group, '/').'[\'"]/', (string) file_get_contents($this->path)) === 1;
    }

    /**
     * Append PHP to the end of the file.
     *
     * @return bool Whether the file could be written.
     */
    public function append(string $php): bool
    {
        // Asked first, and the write suppressed: an unwritable route file is a
        // permissions problem, and PHP answers one with a warning rather than a
        // return value — which under a test runner is an exception out of a
        // step that has a perfectly good way to report it.
        return $this->exists()
            && is_writable($this->path)
            && @file_put_contents($this->path, $php, FILE_APPEND) !== false;
    }
}

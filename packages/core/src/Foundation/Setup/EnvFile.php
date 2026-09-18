<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

/**
 * The application's `.env`, as something a setup step can write one line into.
 *
 * Every switch these steps flip — audit recording, the notification drivers,
 * the settings cache store — is `env(...)` in a published config file, so there
 * are two places to write and only one of them is safe. A published config is
 * PHP: changing a value there means editing source, and the honest ways to do
 * that are a parser or a regular expression over somebody's code. `.env` is
 * key=value, it is the file the config already defers to, and it is the one an
 * application expects an installer to touch.
 *
 * It is also the file that is *not* in version control, which is the right
 * place for "this machine has audit on": the config file is the package's
 * default and stays that way.
 *
 * ## What it will not do
 *
 * There is no delete, and {@see set()} rewrites a line rather than appending a
 * second one — a `.env` with the same key twice is read as whichever came last,
 * which is a bug nobody looks for.
 */
final readonly class EnvFile
{
    public function __construct(private string $path) {}

    public static function forApplication(): self
    {
        return new self(base_path('.env'));
    }

    /**
     * Whether there is a file to write to.
     *
     * A step asks this before offering anything: an application running on real
     * environment variables with no `.env` at all is a deployment, not a broken
     * install, and the answer there is to leave it alone.
     */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * What this key is set to in the file, ignoring the environment around it.
     */
    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        $contents = (string) file_get_contents($this->path);

        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return null;
        }

        return trim($matches[1], " \t\"'");
    }

    /**
     * Set the key, replacing the line it is on or adding one at the end.
     *
     * Values carrying a space, a comma or a `#` are quoted, because a bare one
     * is cut short by the parser at the first of them — a driver list written
     * as `session,database` survives, and `a, b` silently becomes `a,`.
     *
     * @return bool Whether the file could be written.
     */
    public function set(string $key, string $value): bool
    {
        if (! $this->exists() || ! is_writable($this->path)) {
            return false;
        }

        $contents = (string) file_get_contents($this->path);
        $line = $key.'='.$this->quote($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        $contents = preg_match($pattern, $contents) === 1
            ? (string) preg_replace($pattern, $line, $contents, 1)
            : rtrim($contents, "\n")."\n".$line."\n";

        return file_put_contents($this->path, $contents) !== false;
    }

    private function quote(string $value): string
    {
        return preg_match('/[\s,#"\']/', $value) === 1
            ? '"'.str_replace('"', '\"', $value).'"'
            : $value;
    }
}

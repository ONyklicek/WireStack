<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

/**
 * A published config file, as the one line a setup step is allowed to change.
 *
 * {@see EnvFile} is the first answer and the better one: a switch that is
 * `env(...)` in a config file has a key=value home the application already
 * defers to, and writing there leaves the package's default where it is. This
 * class is for the switches that have no such home — a third-party config whose
 * value is a bare literal, `permission.teams` being the one this framework
 * cannot route around. Turning teams on is what puts the team column into the
 * permission cache key, and Spatie reads it from its own published file and
 * from nowhere else.
 *
 * ## It edits somebody's source, so it refuses more than it accepts
 *
 * {@see set()} rewrites one line, and only when the file says that line exactly
 * once in the shape the package shipped it — `'key' => value,` on its own. Two
 * matches means the key appears in more than one section and this cannot know
 * which; none means the application rewrote the file, and a regular expression
 * that keeps looking would eventually match the wrong thing. Both answer false,
 * and a step that gets false says the line to change rather than pretending.
 *
 * Nothing here adds a key. A key that is not in the file is a key this does not
 * understand, and appending one at the end of an array literal is how an
 * installer corrupts a config file somebody has to fix by hand.
 */
final readonly class ConfigFile
{
    public function __construct(private string $path) {}

    /**
     * `config/{$name}.php`, whether or not it is there.
     *
     * Not published is the normal state for a package nobody has run
     * `vendor:publish` for, and {@see exists()} is the question to ask about it.
     */
    public static function forApplication(string $name): self
    {
        return new self(config_path($name.'.php'));
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Set a key whose value is a single-line literal.
     *
     * The value is written as PHP source — `true`, `false`, `'App\Models\Team'`
     * — because that is what the file holds. Callers pass a literal they wrote,
     * never a value they were handed.
     *
     * @param  string  $key  The array key, without quotes.
     * @param  string  $literal  PHP source for the new value.
     * @return bool Whether the line was there, exactly once, and could be written.
     */
    public function set(string $key, string $literal): bool
    {
        if (! $this->exists() || ! is_writable($this->path)) {
            return false;
        }

        $contents = (string) file_get_contents($this->path);

        // `'key' => <anything but a newline>,` on a line of its own. An array or
        // a closure opening on that line does not match, which is the point:
        // replacing the first line of a multi-line value leaves the rest of it
        // stranded in the file as a syntax error.
        $pattern = "/^([ \t]*)'".preg_quote($key, '/')."'\s*=>\s*[^\n]*,[ \t]*$/m";

        if (preg_match_all($pattern, $contents, $matches) !== 1) {
            return false;
        }

        $contents = (string) preg_replace_callback(
            $pattern,
            static fn (array $m): string => $m[1]."'".$key."' => ".$literal.',',
            $contents,
            1,
        );

        return file_put_contents($this->path, $contents) !== false;
    }
}

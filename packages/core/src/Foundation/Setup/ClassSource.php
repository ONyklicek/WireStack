<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Setup;

/**
 * An application class's source file, edited the way a person would edit it.
 *
 * Setup steps need to put a trait on the application's user model — the
 * two-factor columns are worth nothing to a model without
 * `TwoFactorAuthenticatable`, and a passkey table is worth nothing to one
 * without `PasskeyAuthenticatable`. Each package that needed it used to carry a
 * private copy of the same three regular expressions; this is the one copy.
 *
 * Text, not reflection: the file is the application's, and what is written is
 * what a person would have typed — an import among the imports, a `use` at the
 * top of the class body, a name in `implements`. Every edit is idempotent, and
 * one that finds the name already there (in any of the places it can be
 * written) changes nothing.
 *
 * Nothing is written until {@see save()}, so a step can decide from
 * {@see needs()} alone whether it is pending.
 */
final class ClassSource
{
    private string $contents;

    private bool $changed = false;

    public function __construct(private readonly string $path)
    {
        $this->contents = is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** The application's user model, where Laravel puts it — or null. */
    public static function userModel(): ?self
    {
        foreach ([app_path('Models/User.php'), app_path('User.php')] as $path) {
            if (is_file($path)) {
                return new self($path);
            }
        }

        return null;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Whether the class already uses this trait. */
    public function usesTrait(string $trait): bool
    {
        $short = self::short($trait);

        return $this->imports($trait)
            && preg_match('/^[ \t]+use\s+[^;]*\b'.preg_quote($short, '/').'\b[^;]*;/m', $this->contents) === 1;
    }

    /** Whether the class already implements this interface. */
    public function implementsInterface(string $interface): bool
    {
        return $this->imports($interface)
            && preg_match('/\bclass\s+\w+[^{]*\bimplements\b[^{]*\b'.preg_quote(self::short($interface), '/').'\b/', $this->contents) === 1;
    }

    /**
     * Whether either list names something the class does not have yet.
     *
     * @param  array<int, class-string>  $traits
     * @param  array<int, class-string>  $interfaces
     */
    public function needs(array $traits = [], array $interfaces = []): bool
    {
        foreach ($traits as $trait) {
            if (! $this->usesTrait($trait)) {
                return true;
            }
        }

        foreach ($interfaces as $interface) {
            if (! $this->implementsInterface($interface)) {
                return true;
            }
        }

        return false;
    }

    /** Import the trait and use it at the top of the class body. */
    public function addTrait(string $trait): self
    {
        if ($this->usesTrait($trait)) {
            return $this;
        }

        $this->import($trait);

        $this->replace('/(\bclass\s+\w+[^{]*\{)/s', '$1'."\n    use ".self::short($trait).";\n");

        return $this;
    }

    /** Import the interface and add it to the class's `implements`. */
    public function addInterface(string $interface): self
    {
        if ($this->implementsInterface($interface)) {
            return $this;
        }

        $this->import($interface);

        $short = self::short($interface);

        preg_match('/\bclass\s+\w+[^{]*\{/s', $this->contents, $declaration);

        if (($declaration[0] ?? '') === '') {
            return $this;
        }

        $rewritten = str_contains($declaration[0], 'implements')
            ? (string) preg_replace('/\s*\{$/', ', '.$short.'$0', $declaration[0], 1)
            : (string) preg_replace('/\s*\{$/', ' implements '.$short.'$0', $declaration[0], 1);

        $this->contents = str_replace($declaration[0], $rewritten, $this->contents);
        $this->changed = true;

        return $this;
    }

    /** Write the edits, if there were any. False when the file cannot be written. */
    public function save(): bool
    {
        if (! $this->changed) {
            return true;
        }

        if (! is_writable($this->path) || @file_put_contents($this->path, $this->contents) === false) {
            return false;
        }

        $this->changed = false;

        return true;
    }

    public function contents(): string
    {
        return $this->contents;
    }

    private function imports(string $class): bool
    {
        return preg_match('/^use\s+'.preg_quote(ltrim($class, '\\'), '/').'\s*;/m', $this->contents) === 1;
    }

    /** After the last top-level import — or after the namespace, in a file with none. */
    private function import(string $class): void
    {
        if ($this->imports($class)) {
            return;
        }

        $line = 'use '.ltrim($class, '\\').';';

        if (preg_match_all('/^use\s+[^;]+;$/m', $this->contents, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $last = end($matches[0]);
            $at = $last[1] + strlen($last[0]);
            $this->contents = substr($this->contents, 0, $at)."\n".$line.substr($this->contents, $at);
        } else {
            $this->replace('/^(namespace\s+[^;]+;)$/m', "$1\n\n".$line);

            return;
        }

        $this->changed = true;
    }

    private function replace(string $pattern, string $replacement): void
    {
        $result = preg_replace($pattern, $replacement, $this->contents, 1, $count);

        if (is_string($result) && $count === 1) {
            $this->contents = $result;
            $this->changed = true;
        }
    }

    private static function short(string $class): string
    {
        $parts = explode('\\', $class);

        return (string) end($parts);
    }
}

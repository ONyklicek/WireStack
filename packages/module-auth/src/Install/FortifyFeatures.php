<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Install;

/**
 * The `features` list of a published `config/fortify.php`, as lines to switch.
 *
 * `fortify:install` does not publish the config Fortify merges at boot. It
 * publishes a stub, and the stub is a different shape: e-mail verification ships
 * commented out, and two-factor and passkeys open an options array that runs
 * over several lines. A reader that only understood one live
 * `Features::name(),` per line offered two features out of five and could turn
 * none of them on — measured, on the first run in a real application, where the
 * tests had been written against the merged config instead.
 *
 * So a feature here is an entry in one of four shapes, and switching it is
 * commenting or uncommenting every line it spans:
 *
 * ```php
 * Features::registration(),                  // live, one line
 * // Features::emailVerification(),          // off, one line
 * Features::passkeys([                       // live, a block —
 *     'confirmPassword' => true,             //   everything to the line that
 * ]),                                        //   closes it at the same indent
 * ```
 *
 * A comment is `// ` inserted at the entry's own indent, on every non-blank line
 * of it, and uncommenting removes exactly that — so a comment that was already
 * inside a block (`// 'window' => 0,`) survives a round trip as itself.
 *
 * Anything it does not recognise it does not touch: a feature missing from the
 * file is not offered, and a block with no closing line at its indent is left
 * alone rather than guessed at.
 */
final class FortifyFeatures
{
    /** @var array<int, string> */
    private array $lines;

    public function __construct(string $contents)
    {
        $this->lines = explode("\n", $contents);
    }

    /**
     * Which of the named features this file lists, and whether each is on.
     *
     * @param  array<int, string>  $names
     * @return array<string, bool> Feature => on, in the order asked.
     */
    public function states(array $names): array
    {
        $states = [];

        foreach ($names as $name) {
            $entry = $this->find($name);

            if ($entry !== null) {
                $states[$name] = $entry['on'];
            }
        }

        return $states;
    }

    /**
     * Switch one feature on or off. A feature already in that state, or not in
     * the file, is left exactly as it is.
     */
    public function set(string $name, bool $on): void
    {
        $entry = $this->find($name);

        if ($entry === null || $entry['on'] === $on) {
            return;
        }

        for ($i = $entry['start']; $i <= $entry['end']; $i++) {
            $line = $this->lines[$i];

            if (trim($line) === '') {
                continue;
            }

            $head = substr($line, 0, $entry['indent']);
            $rest = substr($line, $entry['indent']);

            if (! $on) {
                $this->lines[$i] = $head.'// '.$rest;
            } elseif (str_starts_with($rest, '// ')) {
                $this->lines[$i] = $head.substr($rest, 3);
            }
        }
    }

    public function contents(): string
    {
        return implode("\n", $this->lines);
    }

    /**
     * Where a feature's entry starts and ends, at what indent, and whether it is on.
     *
     * @return array{start: int, end: int, indent: int, on: bool}|null
     */
    private function find(string $name): ?array
    {
        $opening = '/^([ \t]*)(\/\/ )?Features::'.preg_quote($name, '/').'\((\[?)[^\n]*$/';

        foreach ($this->lines as $index => $line) {
            if (preg_match($opening, $line, $m) !== 1) {
                continue;
            }

            $indent = strlen($m[1]);
            $on = $m[2] === '';
            $prefix = $m[1].($on ? '' : '// ');

            // One line: the call closes where it opens.
            if ($m[3] === '' || str_ends_with(rtrim($line), '),')) {
                return ['start' => $index, 'end' => $index, 'indent' => $indent, 'on' => $on];
            }

            // A block: closed by `]),` at the same indent, commented the same way.
            for ($end = $index + 1, $count = count($this->lines); $end < $count; $end++) {
                if (rtrim($this->lines[$end]) === $prefix.']),') {
                    return ['start' => $index, 'end' => $end, 'indent' => $indent, 'on' => $on];
                }
            }

            return null;
        }

        return null;
    }
}

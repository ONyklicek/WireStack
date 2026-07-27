<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

use NyonCode\WireCore\Foundation\Support\ShortcutLabelFormatter;

/**
 * One row of a keyboard-shortcut legend: a set of equivalent (or paired, like
 * `↑` / `↓`) canonical shortcut strings and the localized description of what
 * they do. Pure data — no markup, no `toHtml()` — so any surface (the table's
 * `?` help, a command palette, a wizard) renders it with its own view.
 */
final class ShortcutHint
{
    /** @var list<string> */
    public readonly array $keys;

    /**
     * @param  array<array-key, string>|string  $keys  canonical shortcut strings (`mod+a`, `shift+ArrowDown`, `?`)
     */
    public function __construct(
        array|string $keys,
        public readonly string $description,
    ) {
        $this->keys = array_values((array) $keys);
    }

    /**
     * @param  array<array-key, string>|string  $keys
     */
    public static function make(array|string $keys, string $description): self
    {
        return new self($keys, $description);
    }

    /**
     * The keys formatted for display on the given platform.
     *
     * @return list<string>
     */
    public function labels(bool $mac = false): array
    {
        return array_map(
            fn (string $key): string => ShortcutLabelFormatter::format($key, $mac),
            $this->keys,
        );
    }

    /**
     * Stable identity for deduplication — the same key set is the same row,
     * regardless of key order or casing.
     */
    public function signature(): string
    {
        $keys = array_map('strtolower', $this->keys);
        sort($keys);

        return implode('|', $keys);
    }
}

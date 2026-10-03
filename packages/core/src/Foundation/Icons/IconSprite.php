<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Icons;

use Closure;
use Throwable;

/**
 * Draws a repeated icon once: a `<symbol>` the first time a render needs it, a
 * `<use>` every time after.
 *
 * A table draws the same few icons in every row — the action buttons, a boolean
 * column, the checkbox mark — and each one used to carry its whole path data.
 * With this switched on (`wire-core.icons.sprite`) an icon rendered inside a
 * scope is the same `<svg>` with the same classes, viewBox, fill/stroke and
 * forwarded attributes, holding `<use href="#wi-…"/>` instead of its body. The
 * body travels once per scope, as a `<symbol>` placed inside the first such
 * `<svg>` of that scope's markup.
 *
 * **A scope is a piece of markup that reaches the page whole**: a Livewire
 * component's render, an island's render, a partial a write answers with. Each
 * one carries the symbols it references, so whichever of them the browser
 * morphs in, the icons in it resolve — no piece depends on markup another
 * response sent. Outside every scope nothing changes: a PDF, a mail, a view
 * rendered to a string inside an action, plain Blade in a layout all get the
 * inline `<svg>` they always did, because nothing would put the symbols next
 * to them.
 *
 * **Why the symbols ride inside an icon and not beside the markup.** A hidden
 * `<svg>` appended to a component's root is a new last child, and that moves
 * every `:last-child`, `last:` and Tailwind 4 `space-y-*` (which spaces all but
 * the last child) in the application's own markup. An `<svg>` that is already
 * there changes nothing anybody styles.
 *
 * Where the carrier icon is later replaced while other references to its
 * symbols stay (an island re-rendered around it), the browser half —
 * `wire-core-icons.js` — has already copied every symbol it saw into one sprite
 * at the end of `<body>`, which `<use>` falls back to by id. It is pushed as a
 * Livewire asset by {@see IconSpriteHook} the first time a component renders a
 * sprited icon, so it needs no layout change to arrive.
 *
 * Bodies that carry ids or `url(#…)` references (gradients, masks, clip paths)
 * are never sprited: a symbol instance resolves them against the document, and
 * two copies of one id are the shape that breaks.
 */
final class IconSprite
{
    /** Every symbol id starts with this; it is also what the scan looks for. */
    public const ID_PREFIX = 'wi-';

    private const USE_OPEN = '<use href="#'.self::ID_PREFIX;

    /**
     * Content-addressed (an id is a hash of the viewBox and body), so a symbol
     * learnt in one request is the right one in every later request of the
     * worker. Kept statically for that reason: a cached render from an earlier
     * request still names its ids, and the scan must be able to define them.
     *
     * @var array<string, ResolvedIcon>
     */
    private static array $symbols = [];

    private int $depth = 0;

    private int $inline = 0;

    public function __construct(private readonly bool $enabled) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /** Whether an icon rendered now should be a reference. */
    public function active(): bool
    {
        return $this->enabled && $this->depth > 0 && $this->inline === 0;
    }

    /**
     * The body of an icon rendered now: `<use>` inside a scope, the icon's own
     * markup outside one or when the body cannot be shared.
     */
    public function body(ResolvedIcon $icon): string
    {
        if (! $this->active()) {
            return $icon->body;
        }

        $id = $icon->spriteId();

        if ($id === null) {
            return $icon->body;
        }

        self::$symbols[$id] ??= $icon;

        return '<use href="#'.$id.'"/>';
    }

    public function open(): void
    {
        if ($this->enabled) {
            $this->depth++;
        }
    }

    /** Close the innermost scope and give its markup the symbols it references. */
    public function close(string $html): string
    {
        if (! $this->enabled) {
            return $html;
        }

        $this->depth = max(0, $this->depth - 1);

        return $this->embed($html);
    }

    /**
     * Render inside a scope of its own and return the finished markup.
     *
     * @param  Closure(): string  $render
     */
    public function scope(Closure $render): string
    {
        $this->open();

        try {
            $html = $render();
        } catch (Throwable $e) {
            $this->depth = max(0, $this->depth - 1);

            throw $e;
        }

        return $this->close($html);
    }

    /**
     * Render with every icon inline, inside a scope or not.
     *
     * For markup that is not HTML when it leaves PHP — an icon serialised into a
     * JSON spec the browser builds a menu from later. A reference there would be
     * escaped, so the scan would still define it, but only if something else in
     * the scope is a live `<svg>` to carry the symbol.
     *
     * @template T
     *
     * @param  Closure(): T  $render
     * @return T
     */
    public function inline(Closure $render): mixed
    {
        $this->inline++;

        try {
            return $render();
        } finally {
            $this->inline--;
        }
    }

    /** Whether a piece of markup references any symbol at all. */
    public static function references(string $html): bool
    {
        return str_contains($html, '#'.self::ID_PREFIX);
    }

    /**
     * Define, inside the markup, every symbol it references and does not define.
     *
     * By scan rather than by bookkeeping of what this scope rendered: markup
     * built once and reused (a row skeleton, an action's cached button) still
     * names its ids when it lands in a later scope, and a scope that only counted
     * its own renders would leave those undefined.
     */
    public function embed(string $html): string
    {
        if (! self::references($html)) {
            return $html;
        }

        preg_match_all('/#('.self::ID_PREFIX.'[0-9a-f]{16})/', $html, $referenced);
        preg_match_all('/<symbol id="('.self::ID_PREFIX.'[0-9a-f]{16})"/', $html, $defined);

        $missing = array_diff(array_unique($referenced[1]), $defined[1]);

        $symbols = '';
        $bodies = [];

        foreach ($missing as $id) {
            $icon = self::$symbols[$id] ?? null;

            if ($icon === null) {
                continue;
            }

            $symbols .= '<symbol id="'.$id.'" viewBox="'.htmlspecialchars($icon->viewBox, ENT_QUOTES).'">'.$icon->body.'</symbol>';
            $bodies['<use href="#'.$id.'"/>'] = $icon->body;
        }

        if ($symbols === '') {
            return $html;
        }

        $carrier = self::carrierOffset($html);

        // Nothing live to carry them — every reference sits in a <template> or a
        // <script>. Those get their bodies back instead: a template's content is
        // not in the document until Alpine stamps it, so a symbol inside it
        // defines nothing.
        if ($carrier === null) {
            return strtr($html, $bodies);
        }

        return substr_replace($html, $symbols, $carrier, 0);
    }

    /**
     * Offset of the first `<use>` that is part of the document as parsed — not
     * inside a `<template>` (inert until stamped) or a `<script>` (text).
     */
    private static function carrierOffset(string $html): ?int
    {
        $inert = null;
        $offset = 0;

        while (($position = strpos($html, self::USE_OPEN, $offset)) !== false) {
            $inert ??= self::inertRanges($html);

            if (! self::within($inert, $position)) {
                return $position;
            }

            $offset = $position + 1;
        }

        return null;
    }

    /**
     * @return list<array{int, int}>
     */
    private static function inertRanges(string $html): array
    {
        if (stripos($html, '<template') === false && stripos($html, '<script') === false) {
            return [];
        }

        preg_match_all('#<(/?)(template|script)\b#i', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $ranges = [];
        $depth = 0;
        $start = 0;
        $kind = null;

        foreach ($tags as $tag) {
            $closing = $tag[1][0] === '/';
            $name = strtolower($tag[2][0]);
            $at = (int) $tag[0][1];

            if ($depth === 0) {
                if (! $closing) {
                    [$depth, $start, $kind] = [1, $at, $name];
                }

                continue;
            }

            // A script's body is text — nothing inside it opens anything. A
            // template may hold templates.
            if ($name !== $kind) {
                continue;
            }

            $depth += $closing ? -1 : ($kind === 'template' ? 1 : 0);

            if ($depth === 0) {
                $ranges[] = [$start, $at];
            }
        }

        if ($depth > 0) {
            $ranges[] = [$start, strlen($html)];
        }

        return $ranges;
    }

    /**
     * @param  list<array{int, int}>  $ranges
     */
    private static function within(array $ranges, int $position): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($position > $start && $position < $end) {
                return true;
            }
        }

        return false;
    }
}

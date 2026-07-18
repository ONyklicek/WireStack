<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * Canonical owner of atomic, record-invariant UI primitive markup.
 *
 * A loading spinner or a success check is byte-identical on every table row, so
 * `@include`ing its Blade partial per row is pure N×View waste (see
 * architecture/plans/render-engine-htmlable-first.md §3). This resolves each
 * primitive once per distinct parameter set and memoises the string, so a caller in
 * a row loop pays one render for the whole table instead of one per row.
 *
 * The spinner's Blade partial stays the single source of markup (and the vendor
 * override point) — it is rendered at most once per (class, wireTarget); the success
 * check delegates to {@see IconManager}, which memoises its own SVG output. This is a
 * container singleton (mirroring `IconManager`) so the memo spans the whole request.
 */
final class Primitives
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(private readonly IconManager $icons) {}

    /**
     * The canonical loading spinner as a ready-to-echo string.
     *
     * Pass a `$wireTarget` only when the spinner itself must carry
     * `wire:loading wire:target="…"`; omit it when an ancestor already gates
     * visibility (a wrapping `wire:loading` element or an Alpine `x-show`), which is
     * the common case in table cells and keeps the string record-invariant.
     */
    public function spinner(string $class = 'h-4 w-4 text-primary-500', ?string $wireTarget = null): string
    {
        return $this->cache['spinner'."\0".$class."\0".((string) $wireTarget)]
            ??= view('wire-core::partials.spinner', [
                'class' => $class,
                'wireTarget' => $wireTarget,
            ])->render();
    }

    /**
     * A success check icon as a ready-to-echo string. Delegates to the canonical
     * icon owner, so it is themeable and its SVG is memoised there.
     */
    public function successCheck(string $class = 'h-4 w-4', string $color = 'text-green-500'): string
    {
        return $this->icons->render('check-circle', $class, $color);
    }
}

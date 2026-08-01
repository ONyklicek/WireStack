<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

/**
 * Canonical owner of an editable cell's server→client sync node.
 *
 * Every editable surface — a table's text/select/toggle column, a panel entry —
 * mounts `wireEditableCell` on a root carrying `wire:ignore.self`, so a Livewire
 * morph cannot stomp the optimistic state it is holding mid-edit. Livewire
 * honours that by leaving the element's OWN attributes alone while still
 * morphing its children, which is why the value the server currently holds and
 * the optimistic-lock version to send with the next write hang off a child node
 * rather than the root. See the partial for what that bought.
 *
 * Rendered the §7 way rather than `@include`d: the markup is one span with two
 * record-varying attributes, so the partial (still the single source of markup,
 * and the vendor override point) is rendered ONCE into a skeleton and each cell
 * only splices its two values in. An include here would be a second Blade view
 * render on every editable row of every table — the exact N×View cost the
 * render-engine work removed, and TablePrimitiveRenderTest fails if it comes
 * back.
 *
 * A container singleton, so the skeleton spans the whole request.
 */
final class CellSync
{
    /**
     * Distinct control-char sentinels, one per position.
     *
     * Both are spliced through `e()`, so they must survive escaping unchanged
     * and must not occur in real content — the same convention the editable-cell
     * skeleton in TextInputColumn uses.
     */
    private const VALUE = "\x01\x01__WCSYNCVAL__\x01\x01";

    private const VERSION = "\x01\x01__WCSYNCVER__\x01\x01";

    private ?string $skeleton = null;

    /**
     * The sync node for one cell, ready to echo.
     *
     * The pair is written together on purpose: the cell's MutationObserver
     * re-reads the value whenever either attribute changes, so a version updated
     * next to a stale value would "reconcile" the cell back to what the page
     * loaded with a moment after a successful save.
     */
    public function node(string $serverValue, string $recordVersion): string
    {
        return strtr($this->skeleton ??= $this->buildSkeleton(), [
            e(self::VALUE) => e($serverValue),
            e(self::VERSION) => e($recordVersion),
        ]);
    }

    private function buildSkeleton(): string
    {
        return view('wire-core::partials.cell-sync', [
            'serverValue' => self::VALUE,
            'recordVersion' => self::VERSION,
        ])->render();
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Illuminate\Contracts\View\View;

/**
 * Canonical render memo for **state-driven** display components — those whose
 * markup is a pure function of a low-cardinality, serialisable state (icon /
 * boolean / badge entries, and their kin).
 *
 * This is the **static/request-scoped** variant of one shared concept — "memoise
 * a state-driven component's view render". Its counterpart, the table's
 * `HasView::renderViewCached` (referenced as plain text to avoid a core→table
 * dependency), is the **instance-scoped** variant. They are kept as two
 * intentionally-scoped variants
 * of the same idea rather than one canonical owner because the cache lifetime
 * differs by render topology and the shared logic is a trivial `$cache[$key] ??= …`:
 * a table column is a single instance that renders every row (instance cache
 * suffices), whereas a repeatable entry clones the schema per row — each row a
 * distinct clone instance — so an instance-level cache cannot collapse the clones.
 * The memo therefore lives in a request-scoped
 * static keyed by the concrete class, the view name, and a signature the
 * component declares. Rows sharing a state collapse to ONE view render
 * (500 rows × 3 statuses → 3 renders, not 500). The cache size is bounded by the
 * number of distinct (class, view, state) tuples, never by row count.
 *
 * Opt-in and safe by construction: {@see renderCacheSignature()} returns null by
 * default, so nothing is cached unless a component proves its output carries no
 * per-record identity (record key, statePath, action wiring). A component that
 * cannot prove that simply returns null and renders normally.
 *
 * Host requirements: the using class exposes `render(): \Illuminate\Contracts\View\View`
 * and `viewName(): string` — satisfied by the Foundation `Component` base class.
 */
trait HasViewRenderCache
{
    /**
     * Request-scoped, shared across clone instances of the same class.
     *
     * @var array<string, string>
     */
    private static array $viewRenderCache = [];

    /**
     * A complete signature of everything that affects this component's rendered
     * markup, or null to opt out of caching (the default).
     *
     * MUST return null whenever the markup embeds per-record identity — a record
     * key, a statePath, or per-entry action wiring — otherwise distinct rows
     * would collapse to one shared (wrong) render. A component overrides this
     * only when its output is a pure function of a low-cardinality, serialisable
     * state.
     */
    protected function renderCacheSignature(): ?string
    {
        return null;
    }

    /**
     * Render to HTML through the memo. For a given signature the output is
     * byte-identical to `render()->render()`; only the render COUNT drops.
     */
    protected function renderCachedHtml(): string
    {
        $signature = $this->renderCacheSignature();

        if ($signature === null) {
            return $this->render()->render();
        }

        return self::$viewRenderCache[static::class."\0".$this->viewName()."\0".$signature]
            ??= $this->render()->render();
    }

    /**
     * Clear the request-scoped memo. For long-lived workers (Octane) between
     * requests and for tests that assert render counts.
     */
    public static function flushViewRenderCache(): void
    {
        self::$viewRenderCache = [];
    }

    abstract public function render(): View;

    abstract protected function viewName(): string;
}

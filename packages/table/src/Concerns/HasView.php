<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Concerns\HasViewRenderCache;

/**
 * Trait HasView
 *
 * Provides a consistent way for columns and actions to render Blade views
 * through the package's view namespace. Supports vendor overrides.
 */
trait HasView
{
    /**
     * The view name for this component.
     * Override in child classes to set a default view.
     */
    protected ?string $view = null;

    /**
     * Set a custom view for this component.
     * Allows complete override of the rendered template.
     *
     * Usage:
     *   TextInputColumn::make('name')->view('my-custom.text-input')
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Get the view name for this component.
     */
    public function getView(): ?string
    {
        return $this->view;
    }

    /**
     * Resolve the fully qualified view name.
     *
     * Resolution order:
     *   1. Custom view set via ->view('my.view')
     *   2. Package namespace view (wire-table::tables.columns.text-input-editable)
     *   3. App-level view fallback (tables.columns.text-input-editable)
     */
    protected function resolveView(string $defaultView): string
    {
        if ($this->view) {
            return $this->view;
        }

        $namespacedView = "wire-table::{$defaultView}";
        if (view()->exists($namespacedView)) {
            return $namespacedView;
        }

        return $defaultView;
    }

    /**
     * Render a Blade view with given data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $defaultView, array $data = []): string
    {
        return view($this->resolveView($defaultView), $data)->render();
    }

    /** @var array<string, string> */
    private array $viewRenderCache = [];

    /**
     * Byte-identical to {@see renderView()}, but memoised by its data payload — the
     * §7 mechanism for **state-driven** cells (Badge/Icon/Boolean/…), whose output is
     * a function of a low-cardinality state. Rows sharing a state produce the same
     * data and reuse one render (500 rows × 4 statuses → 4 renders, not 500). Keying
     * on the actual `$data` needs no "pure function" assumption, so it is always
     * correct. Do NOT use it for content columns (TextColumn) where the data is unique
     * per row — the hash is pure overhead there; use the skeleton splice instead.
     * `$data` values MUST be serialisable (scalars/strings — icon HTML, classes, …).
     *
     * This is the **instance-scoped** variant of one shared concept — "memoise a
     * state-driven component's view render". The core counterpart,
     * {@see HasViewRenderCache}, is the
     * **static/request-scoped** variant for the infolist case where the schema is
     * cloned per row (so an instance cache cannot collapse the clones). They are kept
     * as two intentionally-scoped variants rather than one owner because the cache
     * lifetime differs by render topology (one column instance renders every row here;
     * one clone per row there) and the shared logic is a trivial `$cache[$key] ??= …`.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderViewCached(string $defaultView, array $data = []): string
    {
        return $this->viewRenderCache[$defaultView."\0".md5(serialize($data))]
            ??= $this->renderView($defaultView, $data);
    }
}

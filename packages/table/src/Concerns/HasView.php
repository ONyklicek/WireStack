<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

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
}

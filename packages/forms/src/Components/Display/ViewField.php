<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;

/**
 * Display component that renders a custom Blade view.
 */
class ViewField extends Display
{
    protected ?string $view = null;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $viewData = [];

    protected bool $isHtmlContent = false;

    /** Set the Blade view that renders the field. */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Set the data passed to the view.
     *
     * @param  array<string, mixed>|Closure  $data
     */
    public function viewData(array|Closure $data): static
    {
        $this->viewData = $data;

        return $this;
    }

    /** Escape the content as text instead of rendering it as raw HTML. */
    public function escape(bool $condition = true): static
    {
        $this->isHtmlContent = ! $condition;

        return $this;
    }

    public function getView(): ?string
    {
        return $this->view;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return $this->evaluate($this->viewData);
    }

    public function isHtmlContent(): bool
    {
        return $this->isHtmlContent;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.view-field';
    }
}

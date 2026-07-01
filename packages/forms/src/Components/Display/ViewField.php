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

    protected string|Closure|null $content = null;

    protected bool $isHtmlContent = false;

    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $data
     */
    public function viewData(array|Closure $data): static
    {
        $this->viewData = $data;

        return $this;
    }

    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

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

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
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

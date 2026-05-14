<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;
use NyonCode\WireCore\Foundation\Components\ViewComponent;

/**
 * Placeholder display component for showing text/HTML content without editing.
 */
class Placeholder extends ViewComponent
{
    protected string|Closure|null $content = null;

    protected bool $isHtmlContent = false;

    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function allowHtml(bool $condition = true): static
    {
        $this->isHtmlContent = $condition;

        return $this;
    }

    /**
     * Set content as raw HTML. Content is rendered unescaped.
     *
     * WARNING: Never pass untrusted user input. Sanitize before use.
     */
    public function html(string|Closure $content): static
    {
        $this->content = $content;
        $this->isHtmlContent = true;

        return $this;
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
        return 'wire-forms::components.placeholder';
    }
}

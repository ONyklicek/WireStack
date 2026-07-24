<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Display;

use Closure;

/**
 * Placeholder display component for showing text/HTML content without editing.
 */
class Placeholder extends Display
{
    protected bool $isHtmlContent = false;

    /** Render the content as raw HTML instead of escaped text. */
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

    public function isHtmlContent(): bool
    {
        return $this->isHtmlContent;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.placeholder';
    }
}

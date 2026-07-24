<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * A closure-evaluated body/content string.
 *
 * The output-only sibling of {@see HasLabel} / {@see HasPlaceholder}: owns the
 * `$content` slot, its fluent setter, and a getter that resolves a Closure
 * against the component context. Shared by the display components (Html,
 * Placeholder, Alert, ViewField) and the Callout schema block.
 */
trait HasContent
{
    protected string|Closure|null $content = null;

    /** Set the component's content (a string or a Closure). */
    public function content(string|Closure|null $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->evaluate($this->content);
    }
}

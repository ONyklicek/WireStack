<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Prefix/suffix text and icons for input fields.
 */
trait HasPrefixAndSuffix
{
    protected string|Closure|null $prefix = null;

    protected string|Closure|null $suffix = null;

    protected string|Closure|null $prefixIcon = null;

    protected string|Closure|null $suffixIcon = null;

    public function prefix(string|Closure|null $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string|Closure|null $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function prefixIcon(string|Closure|null $icon): static
    {
        $this->prefixIcon = $icon;

        return $this;
    }

    public function suffixIcon(string|Closure|null $icon): static
    {
        $this->suffixIcon = $icon;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->evaluate($this->prefix);
    }

    public function getSuffix(): ?string
    {
        return $this->evaluate($this->suffix);
    }

    public function getPrefixIcon(): ?string
    {
        return $this->evaluate($this->prefixIcon);
    }

    public function getSuffixIcon(): ?string
    {
        return $this->evaluate($this->suffixIcon);
    }

    public function hasAffix(): bool
    {
        return $this->getPrefix() !== null
            || $this->getSuffix() !== null
            || $this->getPrefixIcon() !== null
            || $this->getSuffixIcon() !== null;
    }
}

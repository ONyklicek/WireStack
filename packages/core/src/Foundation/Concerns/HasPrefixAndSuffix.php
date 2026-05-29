<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Icons\Icon;

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

    public function prefixIcon(string|Icon|Closure|null $icon): static
    {
        $this->prefixIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function suffixIcon(string|Icon|Closure|null $icon): static
    {
        $this->suffixIcon = $icon instanceof Icon ? $icon->value() : $icon;

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
        $value = $this->evaluate($this->prefixIcon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function getSuffixIcon(): ?string
    {
        $value = $this->evaluate($this->suffixIcon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function hasAffix(): bool
    {
        return $this->getPrefix() !== null
            || $this->getSuffix() !== null
            || $this->getPrefixIcon() !== null
            || $this->getSuffixIcon() !== null;
    }
}

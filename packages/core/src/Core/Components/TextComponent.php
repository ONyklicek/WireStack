<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

use Closure;

/**
 * Shared text-specific behavior for TextColumn and TextInput.
 */
class TextComponent extends DataComponent
{
    protected string|Closure|null $placeholder = null;

    protected ?string $prefix = null;

    protected ?string $suffix = null;

    protected ?int $characterLimit = null;

    public function placeholder(string|Closure $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function characterLimit(?int $limit): static
    {
        $this->characterLimit = $limit;

        return $this;
    }

    public function getCharacterLimit(): ?int
    {
        return $this->characterLimit;
    }
}

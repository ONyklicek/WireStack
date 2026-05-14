<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Components;

use Closure;

/**
 * Shared select-specific behavior for SelectColumn and Select field.
 */
class SelectComponent extends DataComponent
{
    /** @var array<string|int, string>|Closure */
    protected array|Closure $options = [];

    protected bool $multiple = false;

    protected string|Closure|null $placeholder = null;

    /**
     * @param  array<string|int, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function placeholder(string|Closure $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }
}

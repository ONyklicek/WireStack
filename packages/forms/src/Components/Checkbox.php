<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Single checkbox field with optional description.
 */
class Checkbox extends Field
{
    protected string|Closure|null $description = null;

    protected bool $inline = false;

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.checkbox';
    }
}

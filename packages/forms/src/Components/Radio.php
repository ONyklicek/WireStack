<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Radio button group field.
 */
class Radio extends Field
{
    /** @var array<string, string>|Closure */
    protected array|Closure $options = [];

    /** @var array<string, string>|Closure */
    protected array|Closure $descriptions = [];

    protected bool $inline = false;

    protected bool $boolean = false;

    /**
     * @param  array<mixed, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  array<string, string>|Closure  $descriptions
     */
    public function descriptions(array|Closure $descriptions): static
    {
        $this->descriptions = $descriptions;

        return $this;
    }

    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    public function boolean(bool $condition = true): static
    {
        $this->boolean = $condition;

        if ($condition) {
            $this->options([
                true => trans('wire-forms::fields.yes'),
                false => trans('wire-forms::fields.no'),
            ]);
        }

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    /**
     * @return array<string, string>
     */
    public function getDescriptions(): array
    {
        return $this->evaluate($this->descriptions);
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function isBoolean(): bool
    {
        return $this->boolean;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.radio';
    }
}

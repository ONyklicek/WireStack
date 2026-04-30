<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Text input field with type variants (email, password, tel, url, numeric, integer).
 *
 * Supports prefix/suffix, mask, datalist, input mode, autocomplete.
 */
class TextInput extends Field
{
    protected string $inputType = 'text';

    protected ?int $minLength = null;

    protected ?int $maxLength = null;

    protected int|float|string|Closure|null $minValue = null;

    protected int|float|string|Closure|null $maxValue = null;

    protected int|float|string|null $step = null;

    protected ?string $mask = null;

    protected ?string $inputMode = null;

    protected ?string $autocomplete = null;

    /** @var array<int, string>|null */
    protected ?array $datalistOptions = null;

    protected bool $isRevealable = false;

    // ─── Type variants ─────────────────────────────────────────────

    public function type(string $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    public function email(): static
    {
        $this->inputType = 'email';
        $this->inputMode = 'email';

        return $this;
    }

    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }

    public function tel(): static
    {
        $this->inputType = 'tel';
        $this->inputMode = 'tel';

        return $this;
    }

    public function url(): static
    {
        $this->inputType = 'url';
        $this->inputMode = 'url';

        return $this;
    }

    public function numeric(): static
    {
        $this->inputType = 'number';
        $this->inputMode = 'decimal';

        return $this;
    }

    public function integer(): static
    {
        $this->inputType = 'number';
        $this->inputMode = 'numeric';
        $this->step = 1;

        return $this;
    }

    // ─── Constraints ───────────────────────────────────────────────

    public function minLength(?int $length): static
    {
        $this->minLength = $length;

        return $this;
    }

    public function maxLength(?int $length): static
    {
        $this->maxLength = $length;

        return $this;
    }

    public function minValue(int|float|string|Closure|null $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function maxValue(int|float|string|Closure|null $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function step(int|float|string|null $step): static
    {
        $this->step = $step;

        return $this;
    }

    // ─── Extras ────────────────────────────────────────────────────

    public function mask(?string $mask): static
    {
        $this->mask = $mask;

        return $this;
    }

    public function inputMode(?string $mode): static
    {
        $this->inputMode = $mode;

        return $this;
    }

    public function autocomplete(?string $value): static
    {
        $this->autocomplete = $value;

        return $this;
    }

    /**
     * @param  array<int, string>  $options
     */
    public function datalist(array $options): static
    {
        $this->datalistOptions = $options;

        return $this;
    }

    public function revealable(bool $condition = true): static
    {
        $this->isRevealable = $condition;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getInputType(): string
    {
        return $this->inputType;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getMinValue(): int|float|string|null
    {
        return $this->evaluate($this->minValue);
    }

    public function getMaxValue(): int|float|string|null
    {
        return $this->evaluate($this->maxValue);
    }

    public function getStep(): int|float|string|null
    {
        return $this->step;
    }

    public function getMask(): ?string
    {
        return $this->mask;
    }

    public function getInputMode(): ?string
    {
        return $this->inputMode;
    }

    public function getAutocomplete(): ?string
    {
        return $this->autocomplete;
    }

    /**
     * @return array<int, string>|null
     */
    public function getDatalistOptions(): ?array
    {
        return $this->datalistOptions;
    }

    public function isRevealable(): bool
    {
        return $this->isRevealable;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.text-input';
    }
}

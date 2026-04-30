<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Closure;

/**
 * Validation support for form field components.
 *
 * Provides rules(), required(), validationMessages(), and related API.
 */
trait HasFormValidation
{
    /** @var array<int, mixed> */
    protected array $rules = [];

    protected bool|Closure $isRequired = false;

    /** @var array<string, string> */
    protected array $validationMessages = [];

    /**
     * @param  array<int, mixed>|string  $rules
     */
    public function rules(array|string $rules): static
    {
        $this->rules = is_array($rules) ? $rules : [$rules];

        return $this;
    }

    public function required(bool|Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    /**
     * @param  array<string, string>  $messages
     */
    public function validationMessages(array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    /**
     * @return array<int, mixed>
     */
    public function getValidationRules(): array
    {
        $rules = $this->rules;

        if ($this->isRequired() && ! in_array('required', $rules)) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }
}

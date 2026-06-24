<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Closure;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;

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

        if ($this instanceof ProvidesImplicitValidationRules && ! $this->hasOptionConstraint($rules)) {
            foreach ($this->implicitValidationRules() as $rule) {
                $rules[] = $rule;
            }
        }

        if ($this->isRequired() && ! in_array('required', $rules)) {
            array_unshift($rules, 'required');
        }

        return $rules;
    }

    /**
     * Whether the field already carries an owner-defined value-set constraint
     * (`in:` / `Rule::in()` / `Rule::enum()`), in which case no implicit one is added.
     *
     * @param  array<int, mixed>  $rules
     */
    private function hasOptionConstraint(array $rules): bool
    {
        foreach ($rules as $rule) {
            if ($rule instanceof In || $rule instanceof Enum) {
                return true;
            }

            if (is_string($rule) && (str_starts_with($rule, 'in:') || str_starts_with($rule, 'enum'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }
}

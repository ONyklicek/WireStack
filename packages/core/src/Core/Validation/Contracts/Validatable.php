<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Validation\Contracts;

interface Validatable
{
    /**
     * Get the validation rules for this component.
     *
     * @return array<string, mixed>
     */
    public function getValidationRules(): array;

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function getValidationMessages(): array;

    /**
     * Get custom attribute names for validation.
     *
     * @return array<string, string>
     */
    public function getValidationAttributes(): array;
}

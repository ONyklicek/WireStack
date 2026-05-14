<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Validation;

use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use NyonCode\WireCore\Core\Validation\Contracts\Validatable;

/**
 * Reusable validation pipeline across forms, tables, and inline components.
 */
final class ValidationPipeline
{
    public function __construct(
        private ?ValidatorFactory $validatorFactory = null,
    ) {}

    /**
     * Validate data against the given rules.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @param  array<string, string>  $attributes
     */
    public function validate(
        array $data,
        array $rules,
        array $messages = [],
        array $attributes = [],
    ): ValidationResult {
        $factory = $this->resolveFactory();
        $validator = $factory->make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            return ValidationResult::failure($validator->errors()->toArray());
        }

        return ValidationResult::success($validator->validated());
    }

    /**
     * Validate data using a component's own rules.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateComponent(Validatable $component, array $data): ValidationResult
    {
        return $this->validate(
            data: $data,
            rules: $component->getValidationRules(),
            messages: $component->getValidationMessages(),
            attributes: $component->getValidationAttributes(),
        );
    }

    /**
     * Validate data against multiple components, merging all results.
     *
     * @param  array<int, Validatable>  $components
     * @param  array<string, mixed>  $data
     */
    public function validateMany(array $components, array $data): ValidationResult
    {
        $result = ValidationResult::success($data);

        foreach ($components as $component) {
            $componentResult = $this->validateComponent($component, $data);
            $result = $result->merge($componentResult);
        }

        return $result;
    }

    private function resolveFactory(): ValidatorFactory
    {
        if ($this->validatorFactory !== null) {
            return $this->validatorFactory;
        }

        /** @var ValidatorFactory */
        return app(ValidatorFactory::class);
    }
}

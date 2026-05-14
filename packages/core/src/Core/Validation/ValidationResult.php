<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Validation;

/**
 * Immutable validation result object.
 */
final readonly class ValidationResult
{
    /**
     * @param  bool  $passed  Whether validation passed.
     * @param  array<string, array<int, string>>  $errors  Validation errors keyed by field.
     * @param  array<string, mixed>  $validatedData  Validated/sanitized data.
     */
    public function __construct(
        private bool $passed,
        private array $errors = [],
        private array $validatedData = [],
    ) {}

    /**
     * Whether validation passed.
     */
    public function passed(): bool
    {
        return $this->passed;
    }

    /**
     * Whether validation failed.
     */
    public function failed(): bool
    {
        return ! $this->passed;
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<int, string>|null
     */
    public function getError(string $field): ?array
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get the validated/sanitized data.
     *
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        return $this->validatedData;
    }

    /**
     * Create a successful validation result.
     *
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data = []): self
    {
        return new self(passed: true, validatedData: $data);
    }

    /**
     * Create a failed validation result.
     *
     * @param  array<string, array<int, string>>  $errors
     */
    public static function failure(array $errors): self
    {
        return new self(passed: false, errors: $errors);
    }

    /**
     * Merge this result with another validation result.
     */
    public function merge(ValidationResult $other): self
    {
        $mergedErrors = $this->errors;

        foreach ($other->errors() as $field => $messages) {
            $mergedErrors[$field] = array_merge($mergedErrors[$field] ?? [], $messages);
        }

        $mergedData = array_merge($this->validatedData, $other->validatedData());
        $passed = $this->passed && $other->passed();

        return new self(passed: $passed, errors: $mergedErrors, validatedData: $mergedData);
    }
}

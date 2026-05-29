<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Closure;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * ModalStep - Defines a step in a multi-step modal wizard.
 *
 * Usage:
 *   ModalStep::make('Základní údaje')
 *       ->description('Vyplňte základní informace')
 *       ->icon('user')
 *       ->schema([
 *           TextInput::make('name')->required(),
 *           TextInput::make('email')->required(),
 *       ])
 *       ->validation([
 *           'name' => 'required|min:3',
 *           'email' => 'required|email',
 *       ])
 *       ->afterValidation(fn ($data) => /* custom validation logic *​/)
 *
 * @phpstan-consistent-constructor
 */
class ModalStep
{
    protected string $label;

    protected ?string $description = null;

    protected ?string $icon = null;

    /** @var array<int, mixed> */
    protected array $schema = [];

    protected ?Closure $schemaCallback = null;

    /** @var array<string, mixed>|null */
    protected ?array $validation = null;

    protected ?Closure $validationCallback = null;

    /** @var array<string, string>|null */
    protected ?array $validationMessages = null;

    protected ?Closure $afterValidationCallback = null;

    protected ?Closure $beforeCallback = null;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public static function make(string $label): static
    {
        return new static($label);
    }

    // Fluent setters
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /**
     * @param  array<int, mixed>|Closure  $schema
     */
    public function schema(array|Closure $schema): static
    {
        if ($schema instanceof Closure) {
            $this->schemaCallback = $schema;
        } else {
            $this->schema = $schema;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $rules
     */
    public function validation(array|Closure $rules): static
    {
        if ($rules instanceof Closure) {
            $this->validationCallback = $rules;
        } else {
            $this->validation = $rules;
        }

        return $this;
    }

    /**
     * @param  array<string, string>|null  $messages
     */
    public function validationMessages(?array $messages): static
    {
        $this->validationMessages = $messages;

        return $this;
    }

    /**
     * Run custom logic after this step's validation passes.
     * Useful for async validation, API calls, etc.
     */
    public function afterValidation(Closure $callback): static
    {
        $this->afterValidationCallback = $callback;

        return $this;
    }

    /**
     * Run custom logic before this step is shown.
     * Useful for pre-filling data based on previous steps.
     */
    public function before(Closure $callback): static
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    // Getters
    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @return array<int, mixed>
     */
    public function getSchema(mixed $context = null): array
    {
        if ($this->schemaCallback && $context) {
            return ($this->schemaCallback)($context);
        }

        return $this->schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidation(mixed $context = null): array
    {
        if ($this->validationCallback && $context) {
            return ($this->validationCallback)($context);
        }

        return $this->validation ?? [];
    }

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array
    {
        return $this->validationMessages ?? [];
    }

    public function getAfterValidationCallback(): ?Closure
    {
        return $this->afterValidationCallback;
    }

    public function getBeforeCallback(): ?Closure
    {
        return $this->beforeCallback;
    }

    /**
     * Convert step to array for frontend config.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $context = null): array
    {
        $schema = $this->getSchema($context);

        // Normalize fields
        $normalizedSchema = array_map(function ($field) use ($context) {
            if (is_object($field) && method_exists($field, 'toArray')) {
                return $field->toArray($context);
            }

            return $field;
        }, $schema);

        return [
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'schema' => $normalizedSchema,
            'validation' => $this->getValidation($context),
            'validationMessages' => $this->validationMessages ?? [],
            'hasAfterValidation' => $this->afterValidationCallback !== null,
            'hasBefore' => $this->beforeCallback !== null,
        ];
    }
}

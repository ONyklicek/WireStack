<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation;

use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Core\Validation\ValidationResult;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Contracts\HasValidation;

/**
 * Collects validation rules from form field components and produces
 * Laravel-compatible rules, messages, and attribute arrays.
 *
 * Delegates actual validation execution to the shared Core ValidationPipeline.
 */
final class FormValidationResolver
{
    /**
     * @param  array<int, Component>  $components  Flat list of field components
     * @param  ?string  $statePath  Form state path prefix
     * @param  array<string, string>  $formMessages  Form-level validation messages
     * @param  array<int, Repeater>  $repeaters  Repeaters, validated via wildcard item paths
     */
    public function __construct(
        private readonly array $components,
        private readonly ?string $statePath = null,
        private readonly array $formMessages = [],
        private readonly array $repeaters = [],
    ) {}

    /**
     * Validate data using the Core ValidationPipeline.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateUsing(array $data): ValidationResult
    {
        $pipeline = new ValidationPipeline;

        return $pipeline->validate(
            data: $data,
            rules: $this->getRules(),
            messages: $this->getMessages(),
            attributes: $this->getAttributes(),
        );
    }

    /**
     * Get all validation rules keyed by state path.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getRules(): array
    {
        $rules = [];

        foreach ($this->components as $component) {
            if (! $component instanceof HasValidation) {
                continue;
            }

            $key = $this->resolveKey($component);
            $componentRules = $component->getValidationRules();
            $rules[$key] = ! empty($componentRules) ? $componentRules : ['nullable'];
        }

        // Repeaters: container rules at the repeater path, child rules at the
        // per-item wildcard path (e.g. "data.contacts.*.label").
        foreach ($this->repeaters as $repeater) {
            $basePath = $repeater->getStatePath();

            $containerRules = $repeater->getContainerValidationRules();
            if ($containerRules !== []) {
                $rules[$basePath] = $containerRules;
            }

            foreach ($repeater->getItemValidationRules() as $childName => $childRules) {
                $rules["{$basePath}.*.{$childName}"] = $childRules;
            }
        }

        return $rules;
    }

    /**
     * Get all custom validation messages.
     *
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        $messages = $this->formMessages;

        foreach ($this->components as $component) {
            if (! $component instanceof HasValidation) {
                continue;
            }

            $key = $this->resolveKey($component);
            $componentMessages = $component->getValidationMessages();

            foreach ($componentMessages as $rule => $message) {
                $messages["{$key}.{$rule}"] = $message;
            }
        }

        return $messages;
    }

    /**
     * Get validation attribute labels.
     *
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        $attributes = [];

        foreach ($this->components as $component) {
            if (! $component instanceof HasValidation) {
                continue;
            }

            $key = $this->resolveKey($component);
            $label = $component->getLabel();

            if ($label !== null) {
                $attributes[$key] = $label;
            }
        }

        return $attributes;
    }

    private function resolveKey(Component&HasValidation $component): string
    {
        $name = $component->getName();

        if ($this->statePath !== null && $this->statePath !== '') {
            return $this->statePath.'.'.$name;
        }

        return $name;
    }
}

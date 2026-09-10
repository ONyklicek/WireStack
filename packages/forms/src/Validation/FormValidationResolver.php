<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation;

use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Core\Validation\ValidationResult;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireForms\Components\Hidden;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Contracts\HasValidation;
use NyonCode\WireForms\Contracts\ProvidesItemValidationRules;

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

            if (! $this->isComponentVisible($component)) {
                continue;
            }

            $key = $this->resolveKey($component);
            $componentRules = $component->getValidationRules();
            $rules[$key] = ! empty($componentRules) ? $componentRules : ['nullable'];

            // A list field's own rules describe the list; these describe one item
            // and mount at the wildcard path, because `max:` cannot mean both a
            // count and a file size at once.
            if ($component instanceof ProvidesItemValidationRules) {
                $itemRules = $component->itemValidationRules();

                if ($itemRules !== []) {
                    $rules["{$key}.*"] = $itemRules;
                }
            }
        }

        // Repeaters: container rules at the repeater path, child rules at the
        // per-item wildcard path (e.g. "data.contacts.*.label").
        foreach ($this->repeaters as $repeater) {
            if (! $this->isComponentVisible($repeater)) {
                continue;
            }

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

            if (! $this->isComponentVisible($component)) {
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

            if (! $this->isComponentVisible($component)) {
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

    /**
     * Hidden components are excluded from validation: a required() rule on a
     * field the user cannot see must never block submit. Visibility is resolved
     * against live state, so a field toggled hidden by another field's value is
     * skipped while it stays hidden.
     *
     * `Hidden` is the exception, because it is a different kind of invisible.
     * The rule above is about a field the user *could* have filled and cannot
     * see right now — a `visibleWhen()` sibling, whose value is nobody's. A
     * `Hidden` is never shown by design and its value is still filled, still
     * carried in the snapshot the browser can edit, and still written on save:
     * its rules are the only thing between the record and whatever came back.
     * Skipping them made `Hidden::make('type')->rules(['in:post,page'])` — the
     * form the docs page recommends — a rule that has never run.
     */
    private function isComponentVisible(object $component): bool
    {
        if ($component instanceof Hidden) {
            return true;
        }

        return ! method_exists($component, 'isVisible') || $component->isVisible();
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

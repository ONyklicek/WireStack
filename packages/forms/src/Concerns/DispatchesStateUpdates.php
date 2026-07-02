<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Contracts\HasStateUpdatedCallback;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Contracts\HasValidation;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Routes a Livewire state-update notification to the matching form field's
 * `afterStateUpdated()` callback.
 *
 * Both hosts that embed forms — standalone {@see WithForms}
 * components and table action modals — receive Livewire `updated` hooks carrying
 * the changed dot-path. This trait is the single canonical place that maps that
 * absolute path to a prepared field and fires its reactive callback, so the two
 * hosts do not each re-implement the lookup.
 *
 * @phpstan-require-extends \Livewire\Component
 */
trait DispatchesStateUpdates
{
    /**
     * @param  iterable<mixed>  $forms
     */
    protected function dispatchAfterStateUpdated(iterable $forms, string $absolutePath, mixed $old): bool
    {
        foreach ($forms as $form) {
            if (! $form instanceof Form) {
                continue;
            }

            // Canonical lookup: resolves flat fields and fields inside repeater
            // items (per-item schema) alike.
            $field = $form->findComponentByStatePath($absolutePath);

            if ($field instanceof Component
                && $field instanceof HasStateUpdatedCallback
                && $field->hasAfterStateUpdated()
            ) {
                $field->runAfterStateUpdated($old);

                return true;
            }
        }

        return false;
    }

    /**
     * Validate a single field that opted into live validation ({@see Field::validateLive()}
     * / {@see Field::validateOnBlur()}) during its reactive roundtrip, refreshing
     * only that field's error bag entry so errors appear/clear as the user types
     * without validating — or flagging — the rest of the form.
     *
     * @param  iterable<mixed>  $forms
     */
    protected function dispatchLiveValidation(iterable $forms, string $absolutePath): bool
    {
        foreach ($forms as $form) {
            if (! $form instanceof Form) {
                continue;
            }

            $field = $form->findComponentByStatePath($absolutePath);

            if ($field instanceof Field
                && $field instanceof HasValidation
                && $field->validatesLive()
            ) {
                $this->validateLiveField($field, $absolutePath);

                return true;
            }
        }

        return false;
    }

    private function validateLiveField(Field&HasValidation $field, string $absolutePath): void
    {
        $rules = $field->getValidationRules();

        $this->resetErrorBag($absolutePath);

        if ($rules === []) {
            return;
        }

        // Nest the current value under its dot-path so a dotted key like
        // "data.email" is matched as nested data by the validator.
        $data = [];
        data_set($data, $absolutePath, data_get($this, $absolutePath));

        $messages = [];
        foreach ($field->getValidationMessages() as $rule => $message) {
            $messages["{$absolutePath}.{$rule}"] = $message;
        }

        $attributes = [];
        if (($label = $field->getLabel()) !== null) {
            $attributes[$absolutePath] = $label;
        }

        $validator = validator($data, [$absolutePath => $rules], $messages, $attributes);

        foreach ($validator->errors()->get($absolutePath) as $message) {
            $this->addError($absolutePath, $message);
        }
    }
}

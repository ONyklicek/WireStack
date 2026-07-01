<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Components\Component as FieldComponent;
use NyonCode\WireForms\Components\Select;

/**
 * Livewire endpoints backing {@see Select::createOptionForm()} and
 * {@see Select::editOptionForm()} — creating a new option or editing the selected
 * one from a modal form.
 *
 * A single option modal is open at a time per kind: {@see $mountedCreateOptionSelect}
 * / {@see $mountedEditOptionSelect} hold the state path of the Select whose modal is
 * showing, and their forms bind to the {@see $createOptionFormData} /
 * {@see $editOptionFormData} bags. The Select is re-resolved from the live form
 * definition on every round trip (its closures cannot be serialised), reusing the
 * field-action resolver.
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithSelectCreation
{
    /** State path of the Select whose create-option modal is currently open. */
    public ?string $mountedCreateOptionSelect = null;

    /** @var array<string, mixed> Bound state for the create-option modal form. */
    public array $createOptionFormData = [];

    /** State path of the Select whose edit-option modal is currently open. */
    public ?string $mountedEditOptionSelect = null;

    /** @var array<string, mixed> Bound state for the edit-option modal form. */
    public array $editOptionFormData = [];

    /**
     * Open the create-option modal for the Select bound to the given state path.
     */
    public function mountCreateOption(string $statePath): void
    {
        $field = $this->resolveFieldForAction($statePath);

        if (! $field instanceof Select || ! $field->hasCreateOptionForm()) {
            return;
        }

        $this->createOptionFormData = [];
        $this->mountedCreateOptionSelect = $statePath;
    }

    /**
     * Validate and persist the mounted Select's new option, select it, and close.
     */
    public function createSelectOption(): void
    {
        $statePath = $this->mountedCreateOptionSelect;

        if ($statePath === null) {
            return;
        }

        $field = $this->resolveFieldForAction($statePath);

        if (! $field instanceof Select || ! $field->hasCreateOptionForm()) {
            $this->unmountCreateOption();

            return;
        }

        $form = $field->getCreateOptionForm($this);

        if ($form === null) {
            $this->unmountCreateOption();

            return;
        }

        // Throws ValidationException on failure, leaving the modal open with errors.
        $form->validate();

        $value = Select::normalizeOptionValue($field->createOption($form->getState()));

        if ($value !== null) {
            $this->selectCreatedOption($field, $statePath, $value);
        }

        $this->unmountCreateOption();
    }

    /**
     * Close the create-option modal and discard its form state.
     */
    public function unmountCreateOption(): void
    {
        $this->mountedCreateOptionSelect = null;
        $this->createOptionFormData = [];
    }

    /**
     * Write the newly-created value into the Select's bound state — appended for a
     * multi-select, replacing the value otherwise.
     */
    protected function selectCreatedOption(Select $field, string $statePath, string|int $value): void
    {
        if ($field->isMultiple()) {
            $current = data_get($this, $statePath, []);
            $current = is_array($current) ? $current : [];
            $current[] = $value;

            data_set($this, $statePath, array_values(array_unique($current)));

            return;
        }

        data_set($this, $statePath, $value);
    }

    /**
     * Open the edit-option modal for the Select bound to the given state path,
     * pre-filling the form with the currently selected option's record. No-op for
     * a multi-select or when nothing is selected.
     */
    public function mountEditOption(string $statePath): void
    {
        $field = $this->resolveFieldForAction($statePath);

        if (! $field instanceof Select || ! $field->hasEditOptionForm() || $field->isMultiple()) {
            return;
        }

        $value = data_get($this, $statePath);

        if ($value === null || $value === '' || is_array($value)) {
            return;
        }

        $this->editOptionFormData = $field->getEditOptionFormData($value);
        $this->mountedEditOptionSelect = $statePath;
    }

    /**
     * Validate and persist the mounted Select's edited option, then close.
     */
    public function updateSelectOption(): void
    {
        $statePath = $this->mountedEditOptionSelect;

        if ($statePath === null) {
            return;
        }

        $field = $this->resolveFieldForAction($statePath);

        if (! $field instanceof Select || ! $field->hasEditOptionForm()) {
            $this->unmountEditOption();

            return;
        }

        $form = $field->getEditOptionForm($this);

        $value = data_get($this, $statePath);

        if ($form === null || $value === null || $value === '' || is_array($value)) {
            $this->unmountEditOption();

            return;
        }

        // Throws ValidationException on failure, leaving the modal open with errors.
        $form->validate();

        $field->updateOption($value, $form->getState());

        $this->unmountEditOption();
    }

    /**
     * Close the edit-option modal and discard its form state.
     */
    public function unmountEditOption(): void
    {
        $this->mountedEditOptionSelect = null;
        $this->editOptionFormData = [];
    }

    /**
     * Provided by {@see InteractsWithFieldActions}, which every forms host also
     * composes. Declared abstract so this trait resolves in isolation.
     */
    abstract protected function resolveFieldForAction(string $statePath): ?FieldComponent;
}

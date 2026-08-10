<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Components\Component as FieldComponent;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Forms\Form;

/**
 * Livewire action endpoint backing field-level actions: affix actions
 * (`suffixAction()` / `prefixAction()` / `hintAction()`) and the Button field.
 *
 * The action's closure cannot be serialised to the client, so the button only
 * carries the field's state path and the action name. On dispatch this trait
 * re-resolves the field from the live form definition, locates the action, and
 * invokes its callback with the field's reactive `$get` / `$set` accessors —
 * the same context afterStateUpdated() receives.
 *
 * Works for both standalone form hosts ({@see WithForms}) and table action
 * modals, which expose their embedded form via getActionModalFormInstance().
 *
 * @phpstan-require-extends Component
 */
trait InteractsWithFieldActions
{
    public function callFieldAction(string $statePath, string $actionName): void
    {
        $field = $this->resolveFieldForAction($statePath);

        // Every action-bearing field is also a state-accessor provider; the
        // combined guard lets the callback always receive $get/$set.
        if (! $field instanceof HasFieldActions || ! $field instanceof HasStateAccessors) {
            return;
        }

        $action = $field->getFieldAction($actionName);

        if ($action === null) {
            return;
        }

        $callback = $action->getActionCallback();

        if ($callback === null) {
            return;
        }

        $accessors = $field->getStateAccessors();

        app()->call($callback, [
            'get' => $accessors['get'] ?? null,
            'set' => $accessors['set'] ?? null,
            'state' => $accessors['state'] ?? null,
            'component' => $field,
            'livewire' => $this,
        ]);
    }

    /**
     * Livewire endpoint backing async searchable selects ({@see Select::getSearchResultsUsing()}).
     *
     * The remote combobox calls this with the field's state path and the current
     * search term; we re-resolve the field from the live form definition and run
     * its search callback, returning a `[value => label]` map for the client to
     * render.
     *
     * @return array<string|int, string>
     */
    public function searchSelectOptions(string $statePath, string $search): array
    {
        $field = $this->resolveFieldForAction($statePath);

        if (! $field instanceof Select || ! $field->hasSearchResultsCallback()) {
            return [];
        }

        return $field->getSearchResults($search);
    }

    /**
     * Locate the field bound to the given state path across every form the host
     * exposes.
     */
    protected function resolveFieldForAction(string $statePath): ?FieldComponent
    {
        return $this->resolveFieldIn($this->fieldActionForms(), $statePath);
    }

    /**
     * Locate a field across the host's own forms only, skipping the forms of any
     * mounted option modal. This is what breaks the cycle: resolving an option
     * form needs its Select looked up first, and looking it up in the full set
     * would re-enter {@see fieldActionForms()}.
     */
    protected function resolveBaseFieldForAction(string $statePath): ?FieldComponent
    {
        return $this->resolveFieldIn($this->baseFieldActionForms(), $statePath);
    }

    /**
     * @param  array<int, Form>  $forms
     */
    private function resolveFieldIn(array $forms, string $statePath): ?FieldComponent
    {
        foreach ($forms as $form) {
            // Canonical lookup: resolves flat fields and fields inside repeater
            // items (per-item schema) alike.
            $component = $form->findComponentByStatePath($statePath);

            if ($component !== null) {
                return $component;
            }
        }

        return null;
    }

    /**
     * Every form whose fields can dispatch actions on this host: the host's own
     * forms, any embedded table action-modal form, and the form of a mounted
     * Select create/edit option modal.
     *
     * The option form belongs here for the same reason the action-modal form
     * does — it is a live form on this host. Leaving it out is what made a
     * `Wizard` inside `createOptionForm()` skip its per-step validation, a
     * nested `Select` fail to reach the search endpoint, and a field action
     * inside an option form resolve to nothing.
     *
     * @return array<int, Form>
     */
    protected function fieldActionForms(): array
    {
        $forms = $this->baseFieldActionForms();

        // Probed rather than required: a host can compose field actions without
        // the option modals, exactly as it can without an action modal.
        if (method_exists($this, 'mountedOptionForms')) {
            foreach ($this->mountedOptionForms() as $optionForm) {
                if ($optionForm instanceof Form) {
                    $forms[] = $optionForm;
                }
            }
        }

        return $forms;
    }

    /**
     * The host's own forms plus any embedded table action-modal form.
     *
     * @return array<int, Form>
     */
    protected function baseFieldActionForms(): array
    {
        $forms = [];

        if (method_exists($this, 'getForms') && method_exists($this, 'resolveForm')) {
            /** @var iterable<mixed> $names */
            $names = $this->getForms();

            foreach ($names as $name) {
                $resolved = $this->resolveForm((string) $name);

                if ($resolved instanceof Form) {
                    $forms[] = $resolved;
                }
            }
        }

        if (method_exists($this, 'getActionModalFormInstance')) {
            $modalForm = $this->getActionModalFormInstance();

            if ($modalForm instanceof Form) {
                $forms[] = $modalForm;
            }
        }

        return $forms;
    }
}

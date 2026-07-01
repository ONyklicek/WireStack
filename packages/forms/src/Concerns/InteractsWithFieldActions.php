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
        foreach ($this->fieldActionForms() as $form) {
            foreach ($form->getFlatComponents() as $component) {
                if ($component->getStatePath() === $statePath) {
                    return $component;
                }
            }
        }

        return null;
    }

    /**
     * Every form whose fields can dispatch actions on this host: the standalone
     * forms plus any embedded table action-modal form.
     *
     * @return array<int, Form>
     */
    protected function fieldActionForms(): array
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

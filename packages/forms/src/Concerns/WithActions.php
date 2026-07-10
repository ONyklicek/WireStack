<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\Concerns\InteractsWithActions;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;

/**
 * Public host trait that lets any Livewire component declare and fully run
 * Actions — including modals, slide-overs, wizards, confirmation, forms, and the
 * whole lifecycle — without wire-table.
 *
 *   class EditPanel extends Component
 *   {
 *       use WithActions;
 *
 *       protected function actions(): array
 *       {
 *           return [$this->editOfferAction()];
 *       }
 *
 *       protected function editOfferAction(): Action
 *       {
 *           return Action::make('editOffer')
 *               ->label('Upravit')->icon('pencil')
 *               ->slideOver()->form([...])
 *               ->action(fn (array $data) => $this->save($data));
 *       }
 *   }
 *
 * In the view, render the actions with `<x-wire-actions::button :action="..." />`
 * (a click auto-mounts the action) and drop `<x-wire-actions::modal-host />` once
 * to render the mounted action's modal/slide-over/wizard/confirmation + form.
 *
 * It lives in wire-forms (not wire-core) because a form-capable host has to
 * compose the wire-forms field concerns; wire-core cannot depend on wire-forms.
 * The form-agnostic engine still lives in wire-core (InteractsWithActions).
 */
trait WithActions
{
    use DispatchesStateUpdates;

    // The form-hosting bridge overrides the core engine's form extension points
    // (no-op in core so a form-free host still works standalone).
    use InteractsWithActionForms, InteractsWithActions {
        InteractsWithActionForms::validateMountedActionForm insteadof InteractsWithActions;
        InteractsWithActionForms::resolveHaltModalForm insteadof InteractsWithActions;
    }
    use InteractsWithFieldActions;
    use InteractsWithFileUploads;
    use InteractsWithNotifications;
    use InteractsWithRepeaters;
    use InteractsWithSelectCreation;
    use InteractsWithWizards;

    /**
     * Meta bag for the currently mounted action (name, currentStep, show).
     *
     * @var array<string, mixed>
     */
    public array $mountedAction = [];

    /**
     * Live form-data bag bound to the action modal's fields. The path
     * `actionModalFormData` matches HasModal's non-table state path so the Form
     * runtime binds and fills correctly.
     *
     * @var array<string, mixed>
     */
    public array $actionModalFormData = [];

    /**
     * Live form-data bag for a halt modal's form.
     *
     * @var array<string, mixed>
     */
    public array $actionModalHaltData = [];

    /**
     * Halt-modal meta bag.
     *
     * @var array<string, mixed>
     */
    public array $mountedHalt = [];

    /** The record a record-scoped action operates on, if any. */
    public ?Model $mountedActionRecord = null;

    // ==========================================
    // Action registry
    // ==========================================

    /**
     * The named actions this component exposes. Override to declare actions.
     *
     * @return array<int, Action>
     */
    protected function actions(): array
    {
        return [];
    }

    /**
     * Resolve a declared action by name. Override for lazy/large registries.
     */
    protected function resolveAction(string $name): ?Action
    {
        foreach ($this->actions() as $action) {
            if ($action instanceof Action && $action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    // ==========================================
    // Public Livewire entry points
    // ==========================================

    /**
     * Mount an action by name. For a modal/slide-over/wizard/confirmation action
     * this opens the modal and seeds the form; for a plain action it runs the
     * callback immediately. Pass `['record' => $model]` to scope it to a record.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function mountAction(string $name, array $arguments = []): void
    {
        $action = $this->resolveAction($name);

        $record = $arguments['record'] ?? null;
        $record = $record instanceof Model ? $record : null;

        if ($action === null || ! $action->canExecute($record)) {
            return;
        }

        $this->mountedActionRecord = $record;

        if (! $action->hasModal()) {
            $this->runStandaloneAction($action, $record, []);
            $this->mountedActionRecord = null;

            return;
        }

        $this->mountedAction = [
            'name' => $name,
            'currentStep' => 0,
            'show' => true,
        ];
        $this->actionModalConfigCache = $action->getModalConfig($record);
        $this->setMountedActionFormData($action->getFormDefaults($record));
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $record);
    }

    /**
     * Validate the mounted action's form and run its callback with the form data.
     */
    public function callMountedAction(): void
    {
        $name = $this->mountedAction['name'] ?? null;

        if (! $name) {
            $this->unmountAction();

            return;
        }

        $action = $this->resolveAction((string) $name);

        if ($action === null) {
            $this->unmountAction();

            return;
        }

        $this->validateMountedActionForm();

        $this->runStandaloneAction($action, $this->mountedActionRecord, $this->getMountedActionFormData());

        $this->unmountAction();
    }

    /**
     * Close the mounted action modal and clear its state.
     */
    public function unmountAction(): void
    {
        $this->closeMountedAction();

        $this->mountedAction = [];
        $this->mountedActionRecord = null;
        $this->actionModalFormInstance = null;
        $this->haltModalFormInstance = null;
    }

    /**
     * Run a resolved action through the shared lifecycle pipeline.
     *
     * @param  array<string, mixed>  $data
     */
    protected function runStandaloneAction(Action $action, ?Model $record, array $data): void
    {
        if (! $action->canExecute($record)) {
            return;
        }

        $payload = ['data' => $data];
        if ($record instanceof Model) {
            $payload['record'] = $record;
        }

        $haltKey = $record instanceof Model ? (string) $record->getKey() : '__action__';

        $this->executeActionPipeline(
            $action,
            $payload,
            $haltKey,
            $record instanceof Model ? 'row' : 'header',
        );
    }

    // ==========================================
    // State seams (backed by public props)
    // ==========================================

    protected function setMountedActionState(string $key, mixed $value): void
    {
        $this->mountedAction[$key] = $value;
    }

    protected function getMountedActionState(string $key, mixed $default = null): mixed
    {
        return $this->mountedAction[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getMountedActionFormData(): array
    {
        return $this->actionModalFormData;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function setMountedActionFormData(array $data): void
    {
        $this->actionModalFormData = $data;
    }

    protected function setMountedActionFormDataValue(string $path, mixed $value): void
    {
        data_set($this->actionModalFormData, $path, $value);
    }

    protected function setHaltModalState(string $key, mixed $value): void
    {
        $this->mountedHalt[$key] = $value;
    }

    /**
     * Resolve the action + record context for the currently mounted action.
     *
     * @return array{0: Action|null, 1: mixed}
     */
    protected function resolveCurrentModalAction(): array
    {
        $name = $this->mountedAction['name'] ?? null;

        if (! $name) {
            return [null, null];
        }

        return [$this->resolveAction((string) $name), $this->mountedActionRecord];
    }
}

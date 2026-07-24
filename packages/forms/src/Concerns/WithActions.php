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
        InteractsWithActionForms::getActionModalFormInstance insteadof InteractsWithActions;
        InteractsWithActionForms::getActionModalFormInstanceForDepth insteadof InteractsWithActions;
    }
    use InteractsWithFieldActions;
    use InteractsWithFileUploads;
    use InteractsWithNotifications;
    use InteractsWithRepeaters;
    use InteractsWithSelectCreation;
    use InteractsWithWizards;

    /**
     * The live modal stack. Each open action modal is one frame; the last entry
     * is the active (top) modal and the rest render live but click-inert behind
     * it. Every frame carries its own meta and its own form-data bag, bound via
     * `wire:model="mountedActions.{depth}.data.*"`, so each level is an
     * independently reactive form and a nested modal can write back into an
     * ancestor's data (see the `$setParent`/`$setFrame` callback bindings).
     *
     * Frame shape:
     *   ['name'=>string, 'currentStep'=>int, 'isBulk'=>bool,
     *    'isHeaderAction'=>bool, 'record'=>['class'=>..,'key'=>..]|null,
     *    'arguments'=>array<string,mixed>, 'data'=>array<string,mixed>]
     *
     * Records are stored as serializable `{class,key}` descriptors (not Model
     * instances, which Livewire does not reliably serialize inside arrays) and
     * re-resolved by key on demand.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $mountedActions = [];

    /**
     * Whether the active (top) modal is shown. The modal-host binds its
     * `wire:model`/entangle to this stable flag rather than a per-frame,
     * depth-indexed path: the active modal element is reused across pushes/pops,
     * so a stable binding avoids an Alpine re-init (which would reset the enter
     * transition and briefly hide a resuming parent). Managed by push/pop.
     */
    public bool $actionModalOpen = false;

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
            if ($action instanceof Action) {
                $found = $this->matchRegisteredAction($action, $name);

                if ($found instanceof Action) {
                    return $found;
                }
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

        if (! $action->hasModal()) {
            $this->runStandaloneAction($action, $record, [], $arguments);

            return;
        }

        // Stack a new live frame on top instead of replacing the current modal
        // (refused only at the runaway safety depth cap).
        if (! $this->canMountAnotherActionFrame()) {
            return;
        }

        // The record is stored as a serializable {class,key} descriptor below and
        // re-exposed to callbacks as $record; drop it from the persisted
        // $arguments bag so a raw Model never re-enters a serialized frame.
        $storedArguments = $arguments;
        unset($storedArguments['record']);

        $this->pushActionFrame([
            'name' => $name,
            'currentStep' => 0,
            'isBulk' => false,
            'isHeaderAction' => $record === null,
            'record' => $this->describeFrameRecord($record),
            'arguments' => $storedArguments,
            'data' => $action->getFormDefaults($record),
        ]);

        $this->actionModalConfigCache = $action->getModalConfig($record);
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $record);
    }

    /**
     * Validate the active modal's form and run its callback with the form data.
     */
    public function callMountedAction(): void
    {
        [$action, $record] = $this->resolveCurrentModalAction();

        if ($action === null) {
            $this->unmountAction();

            return;
        }

        $this->validateMountedActionForm();

        $stackVersionBefore = $this->actionStackVersion;

        $this->runStandaloneAction(
            $action,
            $record instanceof Model ? $record : null,
            $this->getMountedActionFormData(),
            (array) $this->getMountedActionState('arguments', []),
        );

        // Auto-close only if the callback left the stack alone. If it opened a
        // nested modal, closed itself via $close(), or replaced this one, the
        // stack version changed and we leave whatever it settled on in place.
        if ($this->actionStackVersion === $stackVersionBefore) {
            $this->unmountAction();
        }
    }

    /**
     * Close the active modal. Pops the top frame; when a parent frame remains it
     * becomes the active modal (live, with its preserved/returned data), and only
     * when the stack empties is everything torn down.
     */
    public function unmountAction(): void
    {
        $this->closeMountedAction();
    }

    /**
     * Run a resolved action through the shared lifecycle pipeline.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    protected function runStandaloneAction(Action $action, ?Model $record, array $data, array $arguments = []): void
    {
        if (! $action->canExecute($record)) {
            return;
        }

        $payload = ['data' => $data];
        if ($record instanceof Model) {
            $payload['record'] = $record;
        }

        $haltKey = $record instanceof Model ? (string) $record->getKey() : '__action__';

        // Exposed to the callback as `$arguments` when the action has no frame.
        $this->currentActionArguments = $arguments;

        try {
            $this->executeActionPipeline(
                $action,
                $payload,
                $haltKey,
                $record instanceof Model ? 'row' : 'header',
            );
        } finally {
            $this->currentActionArguments = [];
        }
    }

    // ==========================================
    // Frame state seams (backed by $mountedActions)
    // ==========================================

    protected function actionFrameCount(): int
    {
        return count($this->mountedActions);
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    protected function pushActionFrame(array $frame): void
    {
        $this->mountedActions[] = $frame;
        $this->actionModalOpen = true;
        $this->actionStackVersion++;
        $this->resolvedActionFrameCache = [];

        // The incoming frame re-resolves its own form/infolist/config instances.
        $this->actionModalFormInstance = null;
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];
    }

    protected function popActionFrame(): void
    {
        array_pop($this->mountedActions);

        // Stays true while a parent frame remains (it becomes the active modal),
        // flips false only when the stack empties so the modal transitions out.
        $this->actionModalOpen = $this->mountedActions !== [];
        $this->actionStackVersion++;
        $this->resolvedActionFrameCache = [];

        $this->actionModalFormInstance = null;
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];

        if ($this->mountedActions === []) {
            $this->haltModalFormInstance = null;
        }
    }

    /**
     * Mount an action by name at the top of the stack (backs
     * {@see replaceMountedAction()}). The standalone host has a single mount
     * path, so this delegates to {@see mountAction()} — the record travels in
     * `$arguments['record']` exactly as a direct mount.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function mountActionByName(string $name, array $arguments): void
    {
        $this->mountAction($name, $arguments);
    }

    protected function actionFrameStatePath(int $depth): string
    {
        return "mountedActions.{$depth}.data";
    }

    protected function getActionFrameState(int $depth, string $key, mixed $default = null): mixed
    {
        return $this->mountedActions[$depth][$key] ?? $default;
    }

    protected function setActionFrameState(int $depth, string $key, mixed $value): void
    {
        if ($depth < 0 || $depth >= $this->actionFrameCount()) {
            return;
        }

        $this->mountedActions[$depth][$key] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readActionFrameData(int $depth): array
    {
        $data = $this->mountedActions[$depth]['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function setActionFrameData(int $depth, array $data): void
    {
        if ($depth < 0 || $depth >= $this->actionFrameCount()) {
            return;
        }

        $this->mountedActions[$depth]['data'] = $data;
    }

    protected function writeActionFrameData(int $depth, string $path, mixed $value): void
    {
        if ($depth < 0 || $depth >= $this->actionFrameCount()) {
            return;
        }

        $data = $this->readActionFrameData($depth);
        data_set($data, $path, $value);
        $this->mountedActions[$depth]['data'] = $data;
    }

    protected function setHaltModalState(string $key, mixed $value): void
    {
        $this->mountedHalt[$key] = $value;
    }

    /**
     * Per-request memo of resolved `[action, context]` per depth, so one render
     * (config + form + validation reading the same frame) re-resolves the action
     * and re-hydrates its record only once. Not a Livewire property — rebuilt each
     * request; cleared on push/pop since a frame's record never changes in place.
     *
     * @var array<int, array{0: Action|null, 1: mixed}>
     */
    protected array $resolvedActionFrameCache = [];

    /**
     * Resolve the action + record context for the frame at the given depth.
     *
     * @return array{0: Action|null, 1: mixed}
     */
    protected function resolveActionForFrame(int $depth): array
    {
        if (array_key_exists($depth, $this->resolvedActionFrameCache)) {
            return $this->resolvedActionFrameCache[$depth];
        }

        $name = $this->mountedActions[$depth]['name'] ?? null;

        if (! $name) {
            return [null, null];
        }

        $action = $this->resolveAction((string) $name);

        if ($action === null) {
            return [null, null];
        }

        return $this->resolvedActionFrameCache[$depth]
            = [$action, $this->resolveFrameRecord($this->mountedActions[$depth]['record'] ?? null)];
    }

    /**
     * Reduce a record to a serializable `{class, key}` descriptor for a frame.
     * Returns null for a transient/keyless model.
     *
     * @return array{class: class-string<Model>, key: mixed}|null
     */
    protected function describeFrameRecord(?Model $record): ?array
    {
        if ($record === null || $record->getKey() === null) {
            return null;
        }

        return ['class' => $record::class, 'key' => $record->getKey()];
    }

    /**
     * Re-resolve a frame's record descriptor back to a Model instance.
     *
     * @param  array{class: class-string<Model>, key: mixed}|null  $descriptor
     */
    protected function resolveFrameRecord(?array $descriptor): ?Model
    {
        if ($descriptor === null || ! isset($descriptor['class'], $descriptor['key'])) {
            return null;
        }

        $class = $descriptor['class'];

        return $class::query()->find($descriptor['key']);
    }
}

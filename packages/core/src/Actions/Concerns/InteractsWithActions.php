<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Core\Actions\ActionContext;
use NyonCode\WireCore\Core\Actions\ActionPipeline;
use NyonCode\WireCore\Core\Actions\ActionResult;
use NyonCode\WireCore\Core\Events\ActionExecuted;
use NyonCode\WireCore\Core\Events\ActionExecuting;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\ActionExecutingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\Entry;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Modals\ModalStack;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use ReflectionFunction;

/**
 * Canonical, form-agnostic action runtime shared by every action host
 * (wire-table's WithTable and the standalone WithActions host).
 *
 * This concern owns the lifecycle engine that used to live inside WithTable:
 * the reflection-based payload resolver, the Core ActionPipeline bridge
 * (before → action → after → halt → notification → redirect), plugin hooks,
 * lifecycle events, the halt-modal meta bag, and the read-only infolist
 * instance (Infolist ships in core).
 *
 * Everything form-related (validating and rendering a wire-forms Form, wizard
 * steps, footer form submits) lives one layer up in
 * NyonCode\WireForms\Concerns\InteractsWithActionForms, because wire-core must
 * not depend on wire-forms. Hosts compose both.
 *
 * State is stored behind small seams so a wire-table (StateContainer bag under
 * `modal.action.*`) and a plain Livewire component (public array props) can
 * both back the same engine without colliding on one page.
 */
trait InteractsWithActions
{
    /** @var Infolist|null Resolved Infolist instance for the current action modal */
    protected ?Infolist $actionModalInfolistInstance = null;

    /** @var array<string, mixed> Modal config cache — not a public Livewire property */
    protected array $actionModalConfigCache = [];

    // ==========================================
    // State seams (host-provided storage)
    // ==========================================

    /**
     * Write a meta value for the currently mounted action (name, recordKey,
     * isBulk, isHeaderAction, currentStep, show, …).
     */
    abstract protected function setMountedActionState(string $key, mixed $value): void;

    /**
     * Read a meta value for the currently mounted action.
     */
    abstract protected function getMountedActionState(string $key, mixed $default = null): mixed;

    /**
     * The live form-data bag backing the current action modal.
     *
     * @return array<string, mixed>
     */
    abstract protected function getMountedActionFormData(): array;

    /**
     * Replace the whole form-data bag.
     *
     * @param  array<string, mixed>  $data
     */
    abstract protected function setMountedActionFormData(array $data): void;

    /**
     * Write a single dotted value into the form-data bag.
     */
    abstract protected function setMountedActionFormDataValue(string $path, mixed $value): void;

    /**
     * Write a value into the halt-modal meta bag.
     */
    abstract protected function setHaltModalState(string $key, mixed $value): void;

    /**
     * Resolve the action backing the currently open modal together with its
     * record/selection context.
     *
     * @return array{0: Action|BulkAction|HeaderAction|null, 1: mixed}
     */
    abstract protected function resolveCurrentModalAction(): array;

    // ==========================================
    // Modal stacking seams (host-provided storage)
    // ==========================================

    /**
     * Push the currently active action modal onto the suspended stack so a newly
     * mounted action can stack on top of it instead of replacing it. Hosts save
     * the active slot's meta + form-data (and record/context) and reset the
     * non-serialized resolved instances so the incoming action re-resolves.
     */
    abstract protected function suspendCurrentAction(): void;

    /**
     * Restore the most recently suspended (parent) action modal back into the
     * active slot. Returns true when a parent was resumed, false when the
     * suspended stack was empty (i.e. this was the last/only modal).
     */
    abstract protected function resumeSuspendedAction(): bool;

    /**
     * How many parent modals are currently stacked behind the active one.
     */
    abstract protected function suspendedActionCount(): int;

    /**
     * Resolved modal render data for every suspended (parent) modal, ordered
     * shallowest first, so the view can draw a dimmed, inert shell behind the
     * active modal for each stacked level.
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function getSuspendedActionModals(): array;

    /**
     * If an action modal is already open, suspend it before mounting another so
     * the new action stacks on top instead of clobbering the parent. Called by
     * every host mount path.
     *
     * Returns false when the caller must NOT proceed to open a modal: the stack
     * is already at {@see ModalStack::MAX_DEPTH} (a runaway-re-entrancy guard).
     * Returns true when nothing was open, or the open modal was suspended and the
     * caller is free to mount the new one on top.
     */
    protected function suspendActiveActionIfOpen(): bool
    {
        if (! $this->getMountedActionState('show')) {
            return true;
        }

        // active modal + suspended parents; opening one more must not exceed the cap.
        if ($this->suspendedActionCount() >= ModalStack::MAX_DEPTH - 1) {
            return false;
        }

        $this->suspendCurrentAction();

        return true;
    }

    // ==========================================
    // Lifecycle hooks (overridable)
    // ==========================================

    /**
     * Collect the primary keys involved in an action execution, for events and
     * plugin hooks. Defaults to the model's own key; wire-table overrides this
     * to honour a custom table primary key.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    protected function resolveActionRecordIds(array $payload): array
    {
        if (isset($payload['record']) && is_object($payload['record']) && method_exists($payload['record'], 'getKey')) {
            return [$payload['record']->getKey()];
        }

        if (isset($payload['records']) && is_iterable($payload['records'])) {
            $ids = [];
            foreach ($payload['records'] as $record) {
                if (is_object($record) && method_exists($record, 'getKey')) {
                    $ids[] = $record->getKey();
                }
            }

            return $ids;
        }

        return [];
    }

    /**
     * Called after a successful (non-halting) action. Hosts override to refresh
     * their own caches (wire-table invalidates the cached table instance).
     */
    protected function afterActionExecuted(): void
    {
        // No-op by default.
    }

    /**
     * Deliver an action notification. Defaults to the configured notification
     * driver; wire-table overrides to route through its own driver.
     */
    protected function sendActionNotification(Notification $notification): void
    {
        NotificationManager::send($notification);
    }

    /**
     * Give the form-hosting layer a chance to attach a Form instance to a halt
     * modal. No-op in core (form-free); overridden by InteractsWithActionForms.
     */
    protected function resolveHaltModalForm(ActionHalt $halt): void
    {
        // No-op — the wire-forms layer wires up the halt-modal Form instance.
    }

    /**
     * The stable identifier reported in lifecycle events. Defaults to the
     * component class; hosts may override.
     */
    protected function actionEventSourceId(): string
    {
        return static::class;
    }

    // ==========================================
    // Payload resolver
    // ==========================================

    /**
     * Invoke an action callback resolving its parameters by name from the
     * payload (`$record`, `$records`, `$data`, `$set`, `$get`, `$halt`,
     * `$component`, …), falling back to declared defaults.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function invokeActionCallback(callable $callback, array $payload): mixed
    {
        $reflection = new ReflectionFunction($callback);
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $payload)) {
                $arguments[] = $payload[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            }
        }

        return $reflection->invokeArgs($arguments);
    }

    /**
     * Convert an action payload to a Core ActionContext.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function payloadToContext(array $payload, string $actionName): ActionContext
    {
        return new ActionContext(
            record: $payload['record'] ?? null,
            records: $payload['records'] ?? null,
            formData: $payload['data'] ?? [],
            actionName: $actionName,
        );
    }

    /**
     * Convert an ActionContext back to a named-parameter payload for
     * reflection-based callbacks.
     *
     * @return array<string, mixed>
     */
    protected function contextToPayload(ActionContext $ctx): array
    {
        $payload = [];

        if ($ctx->record !== null) {
            $payload['record'] = $ctx->record;
        }
        if ($ctx->records !== null) {
            $payload['records'] = $ctx->records;
        }
        $payload['data'] = $ctx->formData;

        return $payload;
    }

    // ==========================================
    // Pipeline
    // ==========================================

    /**
     * Generalized action execution pipeline.
     *
     * Delegates to the Core ActionPipeline with adapter closures that bridge
     * ActionContext to the named-parameter reflection-based callbacks.
     *
     * @param  Action|BulkAction|HeaderAction  $action  The action to execute
     * @param  array<string, mixed>  $payload  Named arguments for callbacks (record/records/data/…)
     * @param  string  $haltKey  Key for the halt modal ('__bulk__', '__header__', or a record key)
     * @param  string  $actionType  'row', 'bulk', or 'header'
     * @param  bool  $confirmed  Whether this is a confirmed re-execution
     */
    protected function executeActionPipeline(
        mixed $action,
        array $payload,
        string $haltKey,
        string $actionType,
        bool $confirmed = false,
    ): void {
        $data = $payload['data'] ?? [];
        $sourceId = $this->actionEventSourceId();

        $recordIds = $this->resolveActionRecordIds($payload);

        // Plugin hook: action.executing (hooks modify before event reports)
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $manager->runHook('action.executing', [
                'action' => $action,
                'actionName' => $action->getName(),
                'actionType' => $actionType,
                'recordIds' => $recordIds,
                'data' => $data,
                'component' => $this,
            ]);

            $preContext = $this->payloadToContext($payload, $action->getName());
            $manager->runTypedHook(
                'action.executing',
                new ActionExecutingPayload(
                    actionName: $action->getName(),
                    context: $preContext,
                    actionType: $actionType,
                    component: $this,
                ),
            );
        }

        // Dispatch ActionExecuting event (after hooks — reports final state)
        event(new ActionExecuting($sourceId, $action->getName(), $recordIds));

        // Build ActionContext
        $context = $this->payloadToContext($payload, $action->getName());
        $context->set('confirmed', $confirmed);
        $context->set('actionType', $actionType);
        $context->set('haltKey', $haltKey);
        $context->set('component', $this);

        // Wrap before callbacks as adapter closures
        if (! $confirmed && $action->hasBeforeCallbacks()) {
            $wrappedBefore = [];
            foreach ($action->getBeforeCallbacks() as $i => $beforeCallback) {
                $wrappedBefore[] = function (ActionContext $ctx) use ($action, $beforeCallback, $i): mixed {
                    $this->invokeActionCallback($beforeCallback, array_merge(
                        $this->contextToPayload($ctx),
                        ['action' => $action, 'confirmed' => false, 'component' => $this],
                    ));

                    $pendingHalt = $action->consumePendingHalt();
                    if ($pendingHalt) {
                        $pendingHalt->source('before', $i);
                        $ctx->set('pendingHalt', $pendingHalt);

                        return false; // Signals BeforeCallbacksStage to halt
                    }

                    return true;
                };
            }
            $context->set('beforeCallbacks', $wrappedBefore);
        }

        // Wrap after callbacks
        if ($action->hasAfterCallbacks()) {
            $wrappedAfter = [];
            foreach ($action->getAfterCallbacks() as $i => $afterCallback) {
                $wrappedAfter[] = function (ActionContext $ctx, ActionResult $result) use ($action, $afterCallback, $i): void {
                    $this->invokeActionCallback($afterCallback, array_merge(
                        $this->contextToPayload($ctx),
                        ['action' => $action, 'result' => $result, 'confirmed' => $ctx->get('confirmed', false), 'component' => $this],
                    ));

                    $pendingHalt = $action->consumePendingHalt();
                    if ($pendingHalt) {
                        $pendingHalt->source('after', $i);
                        $ctx->set('pendingHalt', $pendingHalt);
                    }
                };
            }
            $context->set('afterCallbacks', $wrappedAfter);
        }

        // Main action closure for the pipeline
        $mainAction = function (ActionContext $ctx) use ($action): mixed {
            $callback = $action->getActionCallback();
            if (! $callback) {
                return ActionResult::success();
            }

            $halt = fn () => ActionHalt::make();
            $result = $this->invokeActionCallback($callback, array_merge(
                $this->contextToPayload($ctx),
                ['halt' => $halt, 'confirmed' => $ctx->get('confirmed', false), 'component' => $this],
            ));

            if ($result instanceof ActionHalt) {
                $result->source('action');
                $ctx->set('pendingHalt', $result);

                return ActionResult::halt();
            }

            return $result instanceof ActionResult ? $result : ActionResult::success();
        };

        // Execute through Core ActionPipeline
        $pipeline = app(ActionPipeline::class);
        $pipelineResult = $pipeline->execute($context, $mainAction);

        // Check for pending halt
        $pendingHalt = $context->get('pendingHalt');
        if ($pendingHalt instanceof ActionHalt) {
            $this->showHaltModal($haltKey, $action->getName(), $pendingHalt, $data, $actionType);

            return;
        }

        // Resulting notification: an explicit ActionResult notification wins;
        // otherwise fall back to the action's declarative success/failure
        // notification config (fires only when a message is configured, so
        // actions without one stay silent).
        $notification = $context->get('notification');
        if ($notification) {
            $this->sendActionNotification(
                Notification::make(
                    $notification['type'] ?? 'success',
                    $notification['message'],
                ),
            );
        } else {
            $notificationContext = $payload['record'] ?? $payload['records'] ?? null;
            $isSuccess = $pipelineResult->isSuccess();

            $configuredMessage = $isSuccess
                ? (method_exists($action, 'getSuccessNotificationMessage') ? $action->getSuccessNotificationMessage($notificationContext) : null)
                : (method_exists($action, 'getFailureNotificationMessage') ? $action->getFailureNotificationMessage($notificationContext) : null);

            if ($configuredMessage !== null && $configuredMessage !== '') {
                $this->sendActionNotification(
                    Notification::make($isSuccess ? 'success' : 'error', $configuredMessage),
                );
            }
        }

        // Handle redirect from pipeline
        $redirect = $context->get('redirect');
        if ($redirect) {
            $this->redirect($redirect);
        }

        // Post-action: bulk deselection
        if ($actionType === 'bulk'
            && method_exists($action, 'shouldDeselectRecordsAfterCompletion')
            && $action->shouldDeselectRecordsAfterCompletion()
            && method_exists($this, 'deselectAllRecords')) {
            $this->deselectAllRecords();
        }

        $this->handleActionSuccess($action, $payload['record'] ?? $payload['records'] ?? null);

        // Plugin hook: action.executed
        if (app()->bound(PluginManager::class)) {
            $manager = app(PluginManager::class);

            $manager->runHook('action.executed', [
                'action' => $action,
                'actionName' => $action->getName(),
                'actionType' => $actionType,
                'recordIds' => $recordIds,
                'result' => $pipelineResult,
                'component' => $this,
            ]);

            $manager->runTypedHook(
                'action.executed',
                new ActionExecutedPayload(
                    actionName: $action->getName(),
                    context: $context,
                    result: $pipelineResult,
                    actionType: $actionType,
                    component: $this,
                ),
            );
        }

        // Dispatch ActionExecuted event
        event(new ActionExecuted($sourceId, $action->getName(), $recordIds, $pipelineResult->isSuccess()));
    }

    /**
     * Show the halt modal with dynamic configuration. Meta lives in core; the
     * optional halt Form instance is wired up by the form-hosting layer.
     *
     * @param  array<string, mixed>  $formData
     */
    protected function showHaltModal(
        string $recordKey,
        string $actionName,
        ActionHalt $halt,
        array $formData = [],
        string $actionType = 'row',
    ): void {
        $this->setHaltModalState('recordKey', $recordKey);
        $this->setHaltModalState('actionName', $actionName);
        $this->setHaltModalState('config', $halt->toArray()['modal']);
        $this->setHaltModalState('formData', $halt->getModalFormData() ?? $formData);
        $this->setHaltModalState('actionType', $actionType);
        $this->setHaltModalState('context', $halt->toArray()['context'] ?? []);

        $this->resolveHaltModalForm($halt);

        $this->setHaltModalState('show', true);
    }

    /**
     * Handle post-action success: host cache refresh and success redirects.
     */
    protected function handleActionSuccess(mixed $action, mixed $record = null): void
    {
        $this->afterActionExecuted();

        if (method_exists($action, 'getSuccessRedirectUrl')) {
            $redirectUrl = $action->getSuccessRedirectUrl($record);
            if ($redirectUrl) {
                $this->redirect($redirectUrl);
            }
        }
    }

    // ==========================================
    // Footer actions (form-free part)
    // ==========================================

    /**
     * Run a custom modal footer action declared via Action::modalFooterActions().
     *
     * The callback receives the live form-data bag as `$data` plus a `$set`
     * writer, `$component`, and the modal's `$context`/`$record`/`$records`.
     * When the footer action opts into `submitsForm()`, the form is validated
     * first (delegated to the form-hosting layer) so validation errors surface
     * before the callback runs.
     */
    public function callModalFooterAction(string $name): void
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        if ($action === null) {
            return;
        }

        $footer = null;
        foreach ($action->getModalFooterActions() as $candidate) {
            if ($candidate instanceof ModalFooterAction && $candidate->getName() === $name) {
                $footer = $candidate;
                break;
            }
        }

        if ($footer === null) {
            return;
        }

        if ($footer->shouldSubmitForm()) {
            // Surfaces validation errors (throws ValidationException) before the callback.
            $this->validateMountedActionForm();
        }

        $callback = $footer->getActionCallback();

        // Capture stacking depth before the callback runs: a footer callback may
        // open a nested modal (which suspends this one), and in that case we must
        // NOT auto-close afterwards — closing would immediately pop the modal the
        // callback just opened.
        $depthBefore = $this->suspendedActionCount();

        if ($callback !== null) {
            $formData = $this->getMountedActionFormData();
            $isBulk = (bool) $this->getMountedActionState('isBulk');
            $isHeader = (bool) $this->getMountedActionState('isHeaderAction');

            $this->invokeActionCallback($callback, [
                'data' => $formData,
                'set' => function (string $path, mixed $value): void {
                    $this->setMountedActionFormDataValue($path, $value);
                },
                'context' => $context,
                'record' => (! $isBulk && ! $isHeader) ? $context : null,
                'records' => $isBulk ? $context : null,
                'component' => $this,
            ]);
        }

        if ($footer->shouldCloseModal() && $this->suspendedActionCount() <= $depthBefore) {
            $this->closeMountedAction();
        }
    }

    /**
     * Validate the current action modal's Form, if any. No-op in core; the
     * wire-forms layer overrides this to run wire-forms validation.
     */
    protected function validateMountedActionForm(): void
    {
        // No-op — the form-hosting layer validates the wire-forms Form.
    }

    /**
     * Close the currently mounted action. When a parent modal is suspended behind
     * it, the parent is resumed into the active slot instead of clearing (modal
     * stacking). Hosts override with their concrete teardown (clearing meta bag,
     * form/infolist instances, caches).
     */
    protected function closeMountedAction(): void
    {
        if ($this->resumeSuspendedAction()) {
            return;
        }

        $this->setMountedActionState('show', false);
        $this->setMountedActionState('name', null);
        $this->setMountedActionState('currentStep', 0);
        $this->setMountedActionFormData([]);
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];
    }

    // ==========================================
    // Modal config + infolist (both core-owned)
    // ==========================================

    /**
     * Get the resolved modal config for the currently mounted action.
     *
     * @return array<string, mixed>
     */
    public function getActionModalData(): array
    {
        if (empty($this->actionModalConfigCache)
            && $this->getMountedActionState('show')
            && $this->getMountedActionState('name')) {
            $this->regenerateModalConfig();
        }

        return $this->actionModalConfigCache;
    }

    /**
     * Regenerate the modal config from the currently mounted action.
     */
    protected function regenerateModalConfig(): void
    {
        [$action, $context] = $this->resolveCurrentModalAction();

        if ($action === null) {
            return;
        }

        $this->actionModalConfigCache = $action->getModalConfig($context);
    }

    /**
     * Whether an action modal is currently mounted/visible. Used by the
     * modal-host view.
     */
    public function isActionModalVisible(): bool
    {
        return (bool) $this->getMountedActionState('show');
    }

    /**
     * The current wizard step index for the mounted action.
     */
    public function getMountedActionStepIndex(): int
    {
        return (int) $this->getMountedActionState('currentStep', 0);
    }

    /**
     * Get the resolved Infolist instance for the current action modal, if any.
     * Re-resolves on demand since it is not serialized between Livewire requests.
     */
    public function getActionModalInfolistInstance(): ?Infolist
    {
        if ($this->actionModalInfolistInstance === null
            && $this->getMountedActionState('show')
            && $this->getMountedActionState('name')) {
            [$action, $context] = $this->resolveCurrentModalAction();

            if ($action !== null) {
                $this->actionModalInfolistInstance = $action->getInfolistInstance($context);
            }
        }

        return $this->actionModalInfolistInstance;
    }

    // ==========================================
    // Infolist actions (entry + section header)
    // ==========================================

    /**
     * Run an interactive action declared on an infolist entry
     * ({@see Entry::actions()}), a section header
     * ({@see Section::headerActions()}), or a repeatable row
     * ({@see RepeatableEntry::actions()}).
     *
     * Infolists are read-only and re-resolved per request, so the button only
     * carries the action name (and, for a repeatable row, its zero-based index).
     * We re-resolve every infolist the host exposes, locate the action-bearing
     * component whose action matches, and invoke its callback with the bound
     * record — mirroring {@see callModalFooterAction()}. For a repeatable row the
     * record is the row item at `$rowKey`. Action names are expected to be unique
     * within an infolist.
     */
    public function callInfolistAction(string $actionName, ?int $rowKey = null): void
    {
        foreach ($this->infolistsForActions() as $infolist) {
            $match = $this->findInfolistAction($infolist->getSchema(), $actionName);

            if ($match === null) {
                continue;
            }

            [$component, $action] = $match;

            $callback = $action->getActionCallback();

            if ($callback === null) {
                return;
            }

            $record = $infolist->getRecord();
            $state = null;

            if ($component instanceof RepeatableEntry && $rowKey !== null) {
                $component->record($infolist->getRecord());
                $record = $component->getRowItems()[$rowKey] ?? null;
                $state = $record;
            } elseif ($component instanceof Entry) {
                $component->record($infolist->getRecord());
                $state = $component->getState();
            }

            $this->invokeActionCallback($callback, [
                'record' => $record,
                'state' => $state,
                'component' => $component,
                'livewire' => $this,
            ]);

            return;
        }
    }

    /**
     * Every infolist whose entries/sections can dispatch actions on this host.
     * Defaults to the current action-modal infolist; standalone infolist hosts
     * override to expose their own.
     *
     * @return array<int, Infolist>
     */
    protected function infolistsForActions(): array
    {
        $infolist = $this->getActionModalInfolistInstance();

        return $infolist instanceof Infolist ? [$infolist] : [];
    }

    /**
     * Depth-first search for the action-bearing component (entry or section)
     * whose {@see HasFieldActions::getFieldAction()} resolves the given name.
     *
     * @param  array<int, mixed>  $components
     * @return array{0: HasFieldActions, 1: Action}|null
     */
    protected function findInfolistAction(array $components, string $actionName): ?array
    {
        foreach ($components as $component) {
            if ($component instanceof HasFieldActions) {
                $action = $component->getFieldAction($actionName);

                if ($action !== null) {
                    return [$component, $action];
                }
            }

            if ($component instanceof LayoutComponent) {
                $nested = $this->findInfolistAction($component->getSchema(), $actionName);

                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BaseAction;
use NyonCode\WireCore\Core\Support\Deprecation;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireCore\Notifications\Notification;

/**
 * The table's action-modal endpoints.
 *
 * What the modal's own controls call — submit, close, run a bulk action, resolve
 * the action a given frame belongs to. A host concern for the same reason
 * {@see InteractsWithTableActions} is: it works on the component's state container.
 *
 * The block at the bottom is filed under "legacy confirmation modal", but only
 * half of it is: `confirmTableAction()`, `executeConfirmedAction()` and
 * `closeConfirmationModal()` are the pre-frame-stack API and are `@deprecated`
 * for v2.0, while the three `*WithData()` methods next to them are live — the
 * halt modal executes through them. The heading is what makes them look alike.
 */
trait InteractsWithTableModals
{
    /**
     * Execute bulk action
     */
    public function executeBulkAction(string $actionName, bool $confirmed = false): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        if ($this->getSelectedRecordsCount() === 0) {
            return;
        }

        // An "all matching" selection is a query: it may be far larger than one
        // request can turn into models. Refusing out loud beats dying halfway
        // through the set, or acting on a silently truncated one.
        if ($this->refuseOversizedBulkSelection()) {
            return;
        }

        $this->executeActionPipeline($action, [
            'records' => $this->getSelectedRecords(),
            'data' => [],
        ], '__bulk__', 'bulk', $confirmed);
    }

    /**
     * Submit action modal (execute action with form data)
     */
    public function submitActionModal(): void
    {
        $isHeaderAction = (bool) $this->getMountedActionState('isHeaderAction');
        $isBulkAction = (bool) $this->getMountedActionState('isBulk');
        $actionName = $this->getMountedActionState('name');
        $recordKey = $this->getMountedActionState('recordKey');
        $formData = $this->getMountedActionFormData();

        if (! $actionName) {
            $this->closeActionModal();

            return;
        }

        // Resolve + validate the active (top) frame's form; the form-hosting
        // layer re-resolves it against the frame's depth-scoped state path and
        // walks wizard steps, so validation error keys line up with the modal.
        [$action] = $this->resolveCurrentModalAction();

        if (! $action) {
            $this->closeActionModal();

            return;
        }

        $this->validateMountedActionForm();

        $stackVersionBefore = $this->actionStackVersion;

        // Execute action
        if ($isHeaderAction) {
            $this->executeHeaderActionWithData($actionName, $formData);
        } elseif ($isBulkAction) {
            $this->executeBulkActionWithData($actionName, $formData);
        } else {
            $this->executeTableActionWithData(
                $recordKey,
                $actionName,
                $formData,
            );
        }

        // Auto-close only if the callback left the stack alone. If it opened a
        // nested modal, closed itself via $close(), or replaced this one, the
        // stack version changed and we leave whatever it settled on in place.
        if ($this->actionStackVersion === $stackVersionBefore) {
            $this->closeActionModal();
        }
    }

    /**
     * Mount an action by name at the top of the stack (backs
     * {@see replaceMountedAction()}). The table resolves the action's kind from
     * its own registries — header, then bulk, then a row action — and routes to
     * the matching open* entry point. A row action needs a record: it is taken
     * from `$arguments['record']` (a Model) or `$arguments['recordKey']`, and
     * {@see replaceMountedAction()} pre-fills the replaced frame's record so a
     * same-record swap needs no explicit key.
     *
     * @param  array<string, mixed>  $arguments
     */
    protected function mountActionByName(string $name, array $arguments): void
    {
        if ($this->findHeaderAction($name) !== null) {
            $this->openHeaderActionModal($name, $arguments);

            return;
        }

        if ($this->findBulkAction($name) !== null) {
            $this->openBulkActionModal($name, $arguments);

            return;
        }

        $record = $arguments['record'] ?? null;
        $recordKey = $record instanceof Model
            ? (string) $record->getKey()
            : ($arguments['recordKey'] ?? null);

        if ($recordKey !== null) {
            unset($arguments['record'], $arguments['recordKey']);
            $this->openActionModal((string) $recordKey, $name, $arguments);
        }
    }

    /**
     * Close the active modal. Pops the top frame; when a parent frame remains it
     * becomes the active modal (live, with its preserved/returned data), and only
     * when the stack empties is everything torn down. Public because the modal
     * host view binds it as the close-action.
     */
    public function closeActionModal(): void
    {
        $this->closeMountedAction();
    }

    // ==========================================
    // Modal stacking seams (StateContainer-backed)
    // ==========================================

    /**
     * Resolve the action + record/selection context for the frame at the given
     * stack depth (bottom-first). Backs the top-frame convenience accessors and
     * the per-frame render config.
     *
     * @return array{0: BaseAction|null, 1: mixed}
     */
    protected function resolveActionForFrame(int $depth): array
    {
        if ($depth < 0) {
            return [null, null];
        }

        if (array_key_exists($depth, $this->resolvedActionFrameCache)) {
            return $this->resolvedActionFrameCache[$depth];
        }

        $name = $this->tableState->get("modal.actions.{$depth}.name");

        if (! $name) {
            return [null, null];
        }

        $isBulk = (bool) $this->tableState->get("modal.actions.{$depth}.isBulk");
        $isHeader = (bool) $this->tableState->get("modal.actions.{$depth}.isHeaderAction");
        $recordKey = $this->tableState->get("modal.actions.{$depth}.recordKey");

        $action = match (true) {
            $isHeader => $this->findHeaderAction((string) $name),
            $isBulk => $this->findBulkAction((string) $name),
            default => $this->findAction((string) $name),
        };

        if ($action === null) {
            return [null, null];
        }

        $context = match (true) {
            $isBulk => $this->getSelectedRecords(),
            $isHeader => null,
            default => $recordKey !== null ? $this->getRecord($recordKey) : null,
        };

        return $this->resolvedActionFrameCache[$depth] = [$action, $context];
    }

    /**
     * Per-request memo of resolved `[action, context]` per depth (see the
     * standalone host's equivalent). Cleared on push/pop and whenever the table
     * cache is invalidated, since a frame's record is re-hydrated from the query.
     *
     * @var array<int, array{0: BaseAction|null, 1: mixed}>
     */
    protected array $resolvedActionFrameCache = [];

    /**
     * Find a record-less action by name.
     *
     * Two surfaces declare actions that run without a record: the toolbar's
     * header actions and the empty state's. Both execute through the same host
     * methods, so both are searched here — without this, an empty-state action
     * would render a button whose click resolves to nothing.
     *
     * The return type spans BaseAction rather than HeaderAction because the
     * empty state also accepts a row Action (that is the declared API); every
     * caller only uses the shared modal/execute surface.
     */
    protected function findHeaderAction(string $actionName): ?BaseAction
    {
        $table = $this->getTable();

        $candidates = [...$table->getHeaderActions(), ...$table->getEmptyStateActions()];

        foreach ($candidates as $action) {
            // Recurse into inline registerActions() so a nested header action
            // declared on another opens by name (parity with findAction).
            $found = $this->matchRegisteredAction($action, $actionName);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Get a single record by its primary key
     */
    public function getRecord(mixed $key): ?object
    {
        if ($key === null) {
            return null;
        }

        $table = $this->getTable();

        return $table->getDataSource()->resolveRecord($key)?->unwrap();
    }

    // ==========================================
    // Legacy Confirmation Modal (Backwards Compatibility)
    // ==========================================

    /**
     * Execute header action with form data
     */
    public function executeHeaderActionWithData(string $actionName, array $data = [], bool $confirmed = false): void
    {
        $action = $this->findHeaderAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        $this->executeActionPipeline($action, [
            'data' => $data,
        ], '__header__', 'header', $confirmed);
    }

    /**
     * Execute bulk action with form data
     */
    public function executeBulkActionWithData(string $actionName, array $data = [], bool $confirmed = false): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->canExecute()) {
            return;
        }

        if ($this->getSelectedRecordsCount() === 0) {
            return;
        }

        if ($this->refuseOversizedBulkSelection()) {
            return;
        }

        $this->executeActionPipeline($action, [
            'records' => $this->getSelectedRecords(),
            'data' => $data,
        ], '__bulk__', 'bulk', $confirmed);
    }

    /**
     * Stop a bulk action whose selection is larger than one request may load,
     * telling the user the number and the cap rather than failing silently.
     */
    protected function refuseOversizedBulkSelection(): bool
    {
        if (! $this->hasTooManySelectedRecords()) {
            return false;
        }

        $this->sendNotification(Notification::error(__('wire-table::messages.bulk_too_many', [
            'count' => $this->getSelectedRecordsCount(),
            'max' => (string) $this->getTable()->getBulkMaxRecords(),
        ])));

        return true;
    }

    /**
     * Execute table action with form data
     */
    public function executeTableActionWithData(
        string $recordKey,
        string $actionName,
        array $data = [],
        bool $confirmed = false,
    ): void {
        $action = $this->findAction($actionName);

        if (! $action) {
            return;
        }

        $table = $this->getTable();
        $record = $table->getDataSource()->resolveRecord($recordKey)?->unwrap();

        if (! $record || ! $action->canExecute($record)) {
            return;
        }

        if ($this->refuseStaleActionRecord($action, $record)) {
            return;
        }

        $this->executeActionPipeline($action, [
            'record' => $record,
            'data' => $data,
        ], $recordKey, 'row', $confirmed);
    }

    /**
     * Refuse a locked action whose record moved while its modal was open.
     *
     * The baseline is the version captured when the frame was pushed, so the
     * window this closes is exactly the one the modal opened: read, type, submit.
     * The frame is popped either way — leaving it up would put the user back in
     * front of a form built from values that are no longer there, with no way to
     * tell that is what they were looking at.
     *
     * Nothing to compare against (an action opened before this shipped, a model
     * without timestamps, or a submit that reached here with no frame mounted)
     * means no refusal: {@see RecordVersion::conflicts()} treats an absent
     * baseline as "the client never had one", the same as it does for a cell.
     * Erring towards letting the write through is the right way round — the
     * alternative is refusing an action nothing is actually wrong with, and a
     * lock that cries wolf gets turned off.
     *
     * Which is also why the baseline is only trusted when the mounted frame is
     * for THIS record. Reading the top of the stack unconditionally would compare
     * one record's version against another's the moment the two disagree, and
     * every such comparison fails — a conflict reported where none happened.
     */
    protected function refuseStaleActionRecord(Action $action, Model $record): bool
    {
        if (! $action->usesOptimisticLock()) {
            return false;
        }

        $baseline = $this->mountedActionBaselineFor($record);

        if (! app(RecordVersion::class)->conflicts($record, $baseline)) {
            return false;
        }

        $this->popActionFrame();

        $this->sendNotification(Notification::warning(
            __('wire-table::messages.record_conflict')
        ));

        return true;
    }

    /**
     * The version captured when the modal for $record opened, or null when the
     * mounted frame is not about that record (or there is no frame at all).
     */
    private function mountedActionBaselineFor(Model $record): ?string
    {
        $mountedKey = $this->getMountedActionState('recordKey');

        if ($mountedKey === null || (string) $mountedKey !== (string) $record->getKey()) {
            return null;
        }

        $baseline = $this->getMountedActionState('recordVersion');

        return is_string($baseline) ? $baseline : null;
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function confirmTableAction(string $recordKey, string $actionName): void
    {
        Deprecation::method('confirmTableAction', 'executeActionPipeline with halt');
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function executeConfirmedAction(): void
    {
        Deprecation::method('executeConfirmedAction', 'submitHaltModal');
    }

    /**
     * @deprecated Use halt modal system instead. Will be removed in v2.0.
     */
    public function closeConfirmationModal(): void
    {
        Deprecation::method('closeConfirmationModal', 'closeHaltModal');
    }
}

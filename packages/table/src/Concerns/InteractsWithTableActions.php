<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\Concerns\InteractsWithActions;
use NyonCode\WireCore\Core\Events\TableRefreshed;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

/**
 * Backing the shared action engine with the table's state container.
 *
 * These are the *seams* {@see InteractsWithActions}
 * declares abstract: the engine owns the pipeline, this says where a table keeps
 * its action frames — a stack under `modal.actions`, with the halt modal under
 * `modal.halt.*`. Host-bound by nature: they read and write the Livewire
 * component's StateContainer, so they cannot become a service.
 *
 * The one thing not to "simplify": `modal.open` is a stable visibility flag kept
 * alongside the stack rather than derived from it. Alpine morphs the modal host,
 * and a flag that appears and disappears resets the component's x-data.
 */
trait InteractsWithTableActions
{
    protected function actionFrameCount(): int
    {
        $frames = $this->tableState->get('modal.actions', []);

        return is_array($frames) ? count($frames) : 0;
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    protected function pushActionFrame(array $frame): void
    {
        $frames = $this->tableState->get('modal.actions', []);
        $frames = is_array($frames) ? $frames : [];
        $frames[] = $frame;
        $this->tableState->set('modal.actions', array_values($frames));
        // Stable visibility flag the modal-host binds to (see WithActions).
        $this->tableState->set('modal.open', true);
        $this->actionStackVersion++;
        $this->resolvedActionFrameCache = [];

        $this->actionModalFormInstance = null;
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];
    }

    protected function popActionFrame(): void
    {
        $frames = $this->tableState->get('modal.actions', []);
        $frames = is_array($frames) ? $frames : [];
        array_pop($frames);
        $this->tableState->set('modal.actions', array_values($frames));
        // Stays true while a parent frame remains; false only when the stack empties.
        $this->tableState->set('modal.open', $frames !== []);
        $this->actionStackVersion++;
        $this->resolvedActionFrameCache = [];

        $this->actionModalFormInstance = null;
        $this->actionModalInfolistInstance = null;
        $this->actionModalConfigCache = [];

        if ($frames === []) {
            $this->haltModalFormInstance = null;
        }

        // Fetch fresh data for the resumed parent (or the underlying table).
        $this->invalidateTable();
    }

    protected function actionFrameStatePath(int $depth): string
    {
        return "tableState.modal.actions.{$depth}.data";
    }

    protected function getActionFrameState(int $depth, string $key, mixed $default = null): mixed
    {
        if ($depth < 0) {
            return $default;
        }

        return $this->tableState->get("modal.actions.{$depth}.{$key}", $default);
    }

    protected function setActionFrameState(int $depth, string $key, mixed $value): void
    {
        if ($depth < 0 || $depth >= $this->actionFrameCount()) {
            return;
        }

        $this->tableState->set("modal.actions.{$depth}.{$key}", $value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readActionFrameData(int $depth): array
    {
        if ($depth < 0) {
            return [];
        }

        $data = $this->tableState->get("modal.actions.{$depth}.data", []);

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

        $this->tableState->set("modal.actions.{$depth}.data", $data);
    }

    protected function writeActionFrameData(int $depth, string $path, mixed $value): void
    {
        if ($depth < 0 || $depth >= $this->actionFrameCount()) {
            return;
        }

        $this->tableState->set("modal.actions.{$depth}.data.{$path}", $value);
    }

    protected function setHaltModalState(string $key, mixed $value): void
    {
        $this->tableState->set('modal.halt.'.$key, $value);
    }

    protected function haltModalFormStatePath(): string
    {
        return 'tableState.modal.halt.formData';
    }

    /**
     * The table honours a custom primary key when collecting affected record IDs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    protected function resolveActionRecordIds(array $payload): array
    {
        $pk = $this->getTable()->getPrimaryKey();

        if (isset($payload['record'])) {
            return [$payload['record']->{$pk}];
        }

        if (isset($payload['records'])) {
            return $payload['records']->pluck($pk)->all();
        }

        return [];
    }

    /**
     * Route action notifications through the table's configured driver.
     */
    protected function sendActionNotification(Notification $notification): void
    {
        $this->sendNotification($notification);
    }

    /**
     * Refresh the cached table instance after a successful action.
     */
    protected function afterActionExecuted(): void
    {
        $this->invalidateTable();

        // A delete may have emptied the current page; step back to the last
        // populated page so the user is not stranded on an empty page.
        $this->clampPageToBounds();
    }

    /**
     * Open action modal (confirmation or form)
     */
    /**
     * @param  array<string, mixed>  $arguments  Exposed to callbacks as `$arguments`.
     */
    public function openActionModal(string $recordKey, string $actionName, array $arguments = []): void
    {
        $action = $this->findAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeTableAction($recordKey, $actionName);

            return;
        }

        $table = $this->getTable();
        $record = $table->getQuery()->where($table->getPrimaryKey(), $recordKey)->first();

        if (! $record) {
            return;
        }

        // Stack a new live frame on top instead of replacing the current modal
        // (refused only at the runaway safety depth cap).
        if (! $this->canMountAnotherActionFrame()) {
            return;
        }

        $this->pushActionFrame([
            'name' => $actionName,
            'recordKey' => $recordKey,
            'isBulk' => false,
            'isHeaderAction' => false,
            'currentStep' => 0,
            'arguments' => $arguments,
            'data' => $action->getFormDefaults($record),
        ]);

        $this->actionModalConfigCache = $action->getModalConfig($record);
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $record);
    }

    /**
     * Find an action by name (including actions inside ActionGroups)
     */
    protected function findAction(string $name): ?Action
    {
        $table = $this->getTable();

        foreach ($table->getActions() as $action) {
            // Check if it's an ActionGroup
            if ($action instanceof ActionGroup) {
                foreach ($action->getActions() as $groupedAction) {
                    if ($groupedAction instanceof Action) {
                        $found = $this->matchRegisteredAction($groupedAction, $name);

                        if ($found instanceof Action) {
                            return $found;
                        }
                    }
                }
            } elseif ($action instanceof Action) {
                $found = $this->matchRegisteredAction($action, $name);

                if ($found instanceof Action) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Execute table action
     */
    public function executeTableAction(string $recordKey, string $actionName, bool $confirmed = false): void
    {
        $action = $this->findAction($actionName);

        if (! $action) {
            return;
        }

        $table = $this->getTable();
        $record = $table->getQuery()->find($recordKey);

        if (! $record || ! $action->canExecute($record)) {
            return;
        }

        $this->executeActionPipeline($action, [
            'record' => $record,
            'data' => [],
        ], $recordKey, 'row', $confirmed);
    }

    /**
     * Invalidate cached table instance so that the next render fetches fresh data.
     */
    public function invalidateTable(): void
    {
        $this->tableInstance = null;
        $this->cachedRecords = null;
        $this->cachedQuery = null;
        $this->queryService = null;
        $this->cachedSelectedRecords = null;
        $this->cachedGroupPartitions = null;
        $this->resolvedActionFrameCache = [];

        event(new TableRefreshed(static::class));
    }

    /**
     * Send a notification through the resolved notification driver.
     */
    public function sendNotification(Notification $notification): void
    {
        $driver = $this->getTable()->getNotificationDriver();

        NotificationManager::send($notification, $driver);
    }

    /**
     * Open bulk action modal
     */
    /**
     * @param  array<string, mixed>  $arguments  Exposed to callbacks as `$arguments`.
     */
    public function openBulkActionModal(string $actionName, array $arguments = []): void
    {
        $action = $this->findBulkAction($actionName);

        if (! $action || ! $action->hasModal()) {
            // No modal, execute directly
            $this->executeBulkAction($actionName);

            return;
        }

        // Get selected records for dynamic form fields/defaults
        $selectedRecords = $this->getSelectedRecords();

        // Stack a new live frame on top instead of replacing the current modal
        // (refused only at the runaway safety depth cap).
        if (! $this->canMountAnotherActionFrame()) {
            return;
        }

        $this->pushActionFrame([
            'name' => $actionName,
            'recordKey' => null,
            'isBulk' => true,
            'isHeaderAction' => false,
            'currentStep' => 0,
            'arguments' => $arguments,
            'data' => $action->getFormDefaults($selectedRecords),
        ]);

        $this->actionModalConfigCache = $action->getModalConfig($selectedRecords);
        $this->actionModalFormInstance = $this->buildModalActionFormInstance($action, $selectedRecords);
    }

    /**
     * Find a bulk action by name
     */
    protected function findBulkAction(string $name): ?BulkAction
    {
        $table = $this->getTable();

        foreach ($table->getBulkActions() as $action) {
            // Recurse into inline registerActions() (parity with findAction).
            $found = $this->matchRegisteredAction($action, $name);

            if ($found instanceof BulkAction) {
                return $found;
            }
        }

        return null;
    }
}

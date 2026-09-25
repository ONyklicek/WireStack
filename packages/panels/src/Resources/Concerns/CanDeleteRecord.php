<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\ForceDeleteAction;
use NyonCode\WireCore\Actions\RestoreAction;
use NyonCode\WirePanels\Resources\Support\RecordAbility;

/**
 * The ready-made *Delete* a record page puts in its header — and, for a resource
 * that manages its trash, *Restore* and *Force delete* beside it.
 *
 * Offered, never drawn by default. Who may delete what is the application's
 * rule — the users module will not delete the last super-admin, whatever the
 * route allows — so a page asks for this one by name:
 *
 *   protected function headerActions(): array
 *   {
 *       return [$this->deleteHeaderAction()];
 *   }
 *
 * The model's policy decides when it has one; without one, deleting is for
 * whoever may open the record's edit page ({@see RecordAbility}). A page with a
 * rule of its own chains it: `->visible(fn () => …)`.
 */
trait CanDeleteRecord
{
    /** Confirm, delete, and go back to the list. Not offered on a record already in the trash. */
    protected function deleteHeaderAction(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (): bool => ! $this->recordIsTrashed())
            ->authorizeUsing(fn (): bool => $this->mayDeleteRecord())
            ->successNotification(__('wire-panels::messages.deleted', ['label' => $this->resourceLabel() ?? '']))
            ->action(fn () => $this->deleteRecord());
    }

    /** Bring a trashed record back, and stay on its page. Offered only on a trashed record. */
    protected function restoreHeaderAction(): RestoreAction
    {
        return RestoreAction::make()
            ->visible(fn (): bool => $this->recordIsTrashed())
            ->authorizeUsing(fn (): bool => $this->mayActOnRecord('restore'))
            ->successNotification(__('wire-panels::messages.restored', ['label' => $this->resourceLabel() ?? '']))
            ->action(function (): void {
                $record = $this->headerActionRecord();

                if ($record !== null && method_exists($record, 'restore')) {
                    $record->restore();
                }
            });
    }

    /** Delete a trashed record for good, then leave its page. Offered only on a trashed record. */
    protected function forceDeleteHeaderAction(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->visible(fn (): bool => $this->recordIsTrashed())
            ->authorizeUsing(fn (): bool => $this->mayActOnRecord('forceDelete'))
            ->successNotification(__('wire-panels::messages.deleted', ['label' => $this->resourceLabel() ?? '']))
            ->action(function (): void {
                $this->headerActionRecord()?->forceDelete();
                $this->leaveForTheList();
            });
    }

    /** Whether the signed-in user may delete the record this page shows. */
    protected function mayDeleteRecord(): bool
    {
        return $this->mayActOnRecord('delete');
    }

    /**
     * Whether the signed-in user may do this to the record: the policy's
     * `$ability` when the model has a policy, the edit page's permission when it
     * has none, and no when there is no edit page either.
     */
    protected function mayActOnRecord(string $ability): bool
    {
        $record = $this->headerActionRecord();

        return $record instanceof Model
            && app(RecordAbility::class)->allows($ability, $record, $this->reachablePageUrl('edit', $record) !== null);
    }

    /** Whether the record this page shows is in the trash. */
    protected function recordIsTrashed(): bool
    {
        $record = $this->headerActionRecord();

        return $record !== null && method_exists($record, 'trashed') && $record->trashed();
    }

    /**
     * Delete the record, then leave its page.
     *
     * What "delete" means stays the model's, so a model with soft deletes
     * soft-deletes. The list is where a deleted record's page leads; an
     * application that routes none stays, on a page whose record is gone.
     */
    protected function deleteRecord(): void
    {
        // Reached only through the action, whose authorization already refused
        // a page with no record to delete.
        $this->headerActionRecord()?->delete();

        $this->leaveForTheList();
    }

    /** Back to the list, where one is routed; a page with none stays put. */
    private function leaveForTheList(): void
    {
        $index = $this->reachablePageUrl('index');

        if ($index !== null) {
            $this->redirect($index, navigate: true);
        }
    }

    abstract protected function headerActionRecord(): ?Model;

    abstract protected function reachablePageUrl(string $page, mixed $record = null): ?string;

    abstract protected function resourceLabel(): ?string;
}

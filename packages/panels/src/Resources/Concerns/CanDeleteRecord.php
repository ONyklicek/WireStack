<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WirePanels\Resources\Support\RecordAbility;

/**
 * The ready-made *Delete* a record page puts in its header.
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
    /** Confirm, delete, and go back to the list. */
    protected function deleteHeaderAction(): DeleteAction
    {
        return DeleteAction::make()
            ->authorizeUsing(fn (): bool => $this->mayDeleteRecord())
            ->successNotification(__('wire-panels::messages.deleted', ['label' => $this->resourceLabel() ?? '']))
            ->action(fn () => $this->deleteRecord());
    }

    /** Whether the signed-in user may delete the record this page shows. */
    protected function mayDeleteRecord(): bool
    {
        $record = $this->headerActionRecord();

        return $record instanceof Model
            && app(RecordAbility::class)->allows('delete', $record, $this->reachablePageUrl('edit', $record) !== null);
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

        $index = $this->reachablePageUrl('index');

        if ($index !== null) {
            $this->redirect($index, navigate: true);
        }
    }

    abstract protected function headerActionRecord(): ?Model;

    abstract protected function reachablePageUrl(string $page, mixed $record = null): ?string;

    abstract protected function resourceLabel(): ?string;
}

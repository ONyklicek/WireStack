<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use Throwable;

/**
 * Class BulkAction - Enhanced with lifecycle hooks, loading state, keyboard shortcuts.
 *
 * Now extends BaseAction which provides HasDynamicProperties (Closure support
 * on label, color, icon, tooltip, size), HasLifecycle, HasModal, etc.
 *
 * @author Ondřej Nyklíček
 */
class BulkAction extends BaseAction
{
    protected bool $deselectRecordsAfterCompletion = true;

    /** Clear the row selection once the bulk action finishes (default true). */
    public function deselectRecordsAfterCompletion(bool $deselect = true): static
    {
        $this->deselectRecordsAfterCompletion = $deselect;

        return $this;
    }

    public function shouldDeselectRecordsAfterCompletion(): bool
    {
        return $this->deselectRecordsAfterCompletion;
    }

    /**
     * @throws Throwable
     */
    public function render(): string
    {
        if (! $this->canExecute()) {
            return '';
        }

        return view('wire-table::tables.actions.bulk-action', ['action' => $this])->render();
    }

    /**
     * @throws Throwable
     */
    public function toHtml(): string
    {
        return $this->render();
    }
}

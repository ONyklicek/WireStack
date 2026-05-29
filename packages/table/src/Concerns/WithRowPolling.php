<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Livewire\Component;

/**
 * Trait WithRowPolling
 *
 * Kept for backwards compatibility.
 * The actual refreshRow() implementation is now in WithTable trait.
 * Components that use WithTable already have refreshRow().
 * This trait can be used by components that need only row polling without the full table.
 *
 * @phpstan-require-extends Component
 *
 * @author Ondřej Nyklíček
 */
trait WithRowPolling
{
    /**
     * Refresh a specific row in the table.
     * This is called by PollColumn for row-level polling.
     *
     * Override in your component for custom refresh logic (e.g. cache invalidation).
     *
     * @param  mixed  $recordKey  The primary key of the record to refresh
     */
    public function refreshRow(mixed $recordKey): void
    {
        // Default: Livewire re-renders automatically.
        // Override for custom logic like cache invalidation.
    }
}

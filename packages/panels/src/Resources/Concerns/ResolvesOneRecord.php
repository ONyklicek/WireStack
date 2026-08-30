<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WirePanels\Exceptions\ResourcePageException;

/**
 * A page that shows exactly one record, and how it finds it.
 *
 * Shared by the edit and view pages because it is genuinely one rule: the record
 * travels as a **key**, not a model. A Livewire component's mount arguments end
 * up in its snapshot, so a hydrated model there is both larger than the key and
 * stale by the time the next request lands — the key travels, the record is
 * resolved per request.
 *
 * Written once rather than on each page: the two are identical today, and two
 * copies of "find the record" is how an authorization scope gets added to one
 * page and not the other.
 */
trait ResolvesOneRecord
{
    /** The record's key. Public because Livewire carries it across requests. */
    public mixed $record = null;

    public function mount(mixed $record = null): void
    {
        $this->record = $record;

        $this->mountedRecord();
    }

    /**
     * Hook for what a page does once its record is known — the edit page seeds
     * its form here, the view page needs nothing.
     */
    protected function mountedRecord(): void {}

    /**
     * The record this page is about.
     *
     * Override to find it another way — a soft-deleted scope, a tenant guard, a
     * non-Eloquent source. The default asks the resource which model it owns and
     * looks the key up against it, which is all it can do without inventing a
     * query the resource never declared.
     */
    protected function resolveRecord(): ?Model
    {
        if ($this->record === null) {
            throw ResourcePageException::missingRecord(static::class);
        }

        // Accepted for the case where a caller already has the model in hand and
        // mounts it directly; it simply does not survive the round trip.
        if ($this->record instanceof Model) {
            return $this->record;
        }

        $resource = static::$resource;
        $model = $resource !== null ? $resource::modelClass() : null;

        if ($model === null) {
            throw ResourcePageException::unresolvableRecord(static::class, (string) $resource);
        }

        return $model::query()->find($this->record);
    }
}

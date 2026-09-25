<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WirePanels\Exceptions\ResourcePageException;
use NyonCode\WirePanels\Resources\Support\TrashedRecords;

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

        $this->abortUnlessRecordExists();

        $this->mountedRecord();
    }

    /**
     * The record a page's actions are about: this one, when it is a model.
     *
     * Here rather than on each page because it is this trait that knows the
     * record — so a page of your own that composes it beside
     * `HostsPageActions` mounts its actions against the record with nothing
     * else written. A record that does not unwrap to a model gives the actions
     * none, because an action's record is a model all the way down.
     */
    protected function headerActionRecord(): ?Model
    {
        $record = $this->nativeRecord();

        return $record instanceof Model ? $record : null;
    }

    /**
     * Hook for what a page does once its record is known — the edit page seeds
     * its form here, the view page needs nothing.
     */
    protected function mountedRecord(): void {}

    /**
     * A key in the URL that reaches no record is a missing page.
     *
     * It used to be a page anyway: `find()` answered null and the page rendered
     * around nothing — an empty view with a 200, or, where an infolist entry
     * needs the record, a 500 from deep inside a Blade view. Asked once, on the
     * way in, through the page's own `resolveRecord()`, so a page that scopes
     * its lookup ({@see ResolvesScopedRecord}) is asked its own question.
     *
     * Only on mount. A record deleted while the page is open is the page's to
     * deal with on the next round trip, as it always was.
     */
    private function abortUnlessRecordExists(): void
    {
        if ($this->record === null || $this->record instanceof Model || $this->record instanceof RecordContract) {
            return;
        }

        $resource = static::$resource;

        if ($resource === null || $resource::modelClass() === null) {
            return;
        }

        if ($this->resolveRecord() === null) {
            abort(404);
        }
    }

    /**
     * The record this page is about.
     *
     * Override to find it another way — a soft-deleted scope, a tenant guard, a
     * non-Eloquent source. The default asks the resource which model it owns and
     * looks the key up against it, which is all it can do without inventing a
     * query the resource never declared.
     *
     * **The return type says `RecordContract` because the sentence above
     * promised it and the signature did not deliver it.** It read `?Model`, so
     * "override for a non-Eloquent source" was advice with nowhere to go — the
     * override could not return what it found. A page fed by a read model, a DTO
     * or an API now returns an `ArrayRecord` or a `RecordContract` of its own.
     *
     * What each page does with the answer differs, and that is where the
     * remaining Eloquent assumption lives: a view page hands it to an infolist,
     * which takes `mixed` and asks the contract; an **edit page hands it to a
     * form**, and the form's save lifecycle is Eloquent — so a non-Eloquent
     * record there must either unwrap to a model or the page must give the form
     * a command of its own through `Form::using()`. `EditPage` refuses the
     * middle case rather than failing inside the save.
     */
    protected function resolveRecord(): Model|RecordContract|null
    {
        if ($this->record === null) {
            throw ResourcePageException::missingRecord(static::class);
        }

        // Accepted for the case where a caller already has the record in hand and
        // mounts it directly; it simply does not survive the round trip, which is
        // why the property holds a key the rest of the time.
        if ($this->record instanceof Model || $this->record instanceof RecordContract) {
            return $this->record;
        }

        $resource = static::$resource;
        $model = $resource !== null ? $resource::modelClass() : null;

        if ($model === null) {
            throw ResourcePageException::unresolvableRecord(static::class, (string) $resource);
        }

        $query = $model::query();

        // A resource that manages its trash has pages for trashed records too:
        // the list offers *Restore* on them, and the record's own page must not
        // answer that with a 404.
        if (TrashedRecords::managedBy($resource)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->find($this->record);
    }

    /**
     * The record as the native object behind it, when there is one.
     *
     * What a relation manager is mounted with: those query relations, so they
     * want the model rather than the contract wrapping it. A source with nothing
     * native to give hands back the contract itself and the relation manager
     * refuses on its own terms — which is the honest place for that failure,
     * since declaring relation managers on a source that has no relations is the
     * mistake being reported.
     */
    protected function nativeRecord(): mixed
    {
        $record = $this->resolveRecord();

        return $record instanceof RecordContract ? ($record->unwrap() ?? $record) : $record;
    }

    /**
     * The record as an Eloquent model, or a refusal naming what to do instead.
     *
     * @throws ResourcePageException When the record does not unwrap to a model.
     */
    protected function requireEloquentRecord(): ?Model
    {
        $record = $this->resolveRecord();

        if ($record === null || $record instanceof Model) {
            return $record;
        }

        // Everything past the guard above is a RecordContract, by the return
        // type of resolveRecord(). What is in question is what it wraps.
        if (($native = $record->unwrap()) instanceof Model) {
            return $native;
        }

        throw ResourcePageException::recordIsNotEloquent(static::class, $record::class);
    }

    /**
     * The record's attributes, whichever kind of record it is.
     *
     * @return array<string, mixed>
     */
    protected function recordAttributes(): array
    {
        $record = $this->resolveRecord();

        return match (true) {
            $record instanceof RecordContract => $record->toArray(),
            $record instanceof Model => $record->attributesToArray(),
            default => [],
        };
    }
}

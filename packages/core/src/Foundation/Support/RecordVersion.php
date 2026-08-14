<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The optimistic-locking convention shared by every inline-edit surface.
 *
 * A record's "version" is its `updated_at` timestamp as a string. The client
 * holds the version it read, sends it back with the edit, and the write is
 * rejected if the record moved underneath it.
 *
 * Small, but it is domain knowledge rather than a helper, and it was written out
 * twice — once in the table's updateTableCell(), once in the panel's
 * updatePanelEntry() — plus eight hand-rolled copies of the stamp itself. Three
 * details have to agree across both, and none of them are obvious:
 *
 *  - a model with no `updated_at` has no version, and is therefore unguarded;
 *  - `'0'` is the client's "I never had a version" sentinel, not a timestamp —
 *    treating it as one would reject every first edit;
 *  - the comparison is on the string, because that is what crosses the wire.
 *
 * ### Edits this request made itself are not someone else's edit
 *
 * The lock exists to catch a record moving under a client between the render it
 * read and the write it sent. It must NOT fire on a record this same request
 * moved one call earlier — and that case is not exotic, it is what a user
 * produces by tabbing out of one cell straight into another on the same row:
 *
 *  - Livewire bundles calls made in one tick into a SINGLE request and runs them
 *    in order, so both edits arrive together;
 *  - the first write moves `updated_at`;
 *  - the second still carries the stamp read at render time and, compared only
 *    against the record's current value, looks stale.
 *
 * The client cannot fix this itself. `wire-editable-committed` — the broadcast
 * that teaches sibling cells the new version — is dispatched from the commit's
 * RESPONSE handler, which is long after a sibling in the same request was
 * serialised into the payload.
 *
 * So the first stamp seen for a record in a request is remembered as its
 * baseline, and a client version equal to that baseline is accepted however many
 * times the request has written since. A version that matches neither the
 * current stamp nor the baseline is still a genuine conflict, which keeps the
 * cross-client guarantee exactly as strict as it was.
 */
final class RecordVersion
{
    /**
     * The stamp each record carried when this request first looked at it.
     *
     * @var array<string, string>
     */
    private array $baselines = [];

    /** The version stamp a client should hold for this record, if it has one. */
    public function stamp(Model $record): ?string
    {
        // Via getUpdatedAtColumn() rather than ->updated_at: a model is free to
        // name the column something else, and the old hand-rolled copies read the
        // literal attribute, so such a record silently had no version at all —
        // which left it unguarded rather than merely unstamped.
        $updatedAt = $record->getAttribute($record->getUpdatedAtColumn());

        return $updatedAt instanceof DateTimeInterface
            ? (string) $updatedAt->getTimestamp()
            : null;
    }

    /**
     * Whether the record moved since the client read the value it is editing.
     *
     * False when the client sent no version, when it sent the `'0'` sentinel, or
     * when the model is not timestamped — in all three there is nothing to
     * compare and the edit proceeds.
     *
     * Also false when the client's version is the one this record carried at the
     * start of the request, even if the request has since moved it: an edit this
     * request made is not another client's edit. See the class docblock for the
     * case that produces — two cells of one row committing in the same tick.
     */
    public function conflicts(Model $record, ?string $clientVersion): bool
    {
        if ($clientVersion === null || $clientVersion === '0') {
            return false;
        }

        $current = $this->stamp($record);

        if ($current === null) {
            return false;
        }

        $identity = $this->identify($record);

        // Recorded on the way past, so the baseline is always the stamp from
        // BEFORE this request's first write to the record: every caller checks
        // the lock before it writes.
        $baseline = $identity === null
            ? $current
            : $this->baselines[$identity] ??= $current;

        if ($current === $clientVersion) {
            return false;
        }

        return $baseline !== $clientVersion;
    }

    /**
     * Forget the baselines — i.e. begin a new request.
     *
     * An app never calls this: the container that owns this singleton is rebuilt
     * per request, so the baselines go with it. Two places have no such boundary
     * and must draw it themselves:
     *
     *  - Octane, where the worker outlives the request. Wired to
     *    `RequestTerminated` next to the other per-request memos, because a
     *    baseline held into the next request would forgive a version that has
     *    genuinely gone stale by then.
     *  - a test that drives two requests' worth of calls through one process, and
     *    means them to be two requests.
     */
    public function flush(): void
    {
        $this->baselines = [];
    }

    /**
     * Identity that survives the record being re-resolved between calls.
     *
     * Null for an unsaved record, which then gets no baseline at all. The obvious
     * fallback — `spl_object_id()` — is worse than nothing here: PHP reuses those
     * ids once an object is collected, so a later unsaved record could inherit an
     * unrelated baseline. A record with no key has no stable identity across the
     * calls this exists to connect, and nothing to be stale against either.
     */
    private function identify(Model $record): ?string
    {
        $key = $record->getKey();

        return $key === null ? null : $record::class.':'.((string) $key);
    }
}

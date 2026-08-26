<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

/**
 * One row, without saying it is an Eloquent model.
 *
 * The engine resolves and reads rows through this so a table fed by a read
 * model, a DTO or an API can answer an action's "which record was that?" the
 * same way Eloquent does.
 *
 * **It stops at the user boundary.** `ActionContext::getRecord()` still hands a
 * closure a `Model` — see ADR 0019's amended invariant 3. Taken literally, "no
 * `Model` anywhere" would break every action closure ever written, and
 * standalone actions worst, since an action on an infolist entry or a form
 * field receives whatever its host holds. So this is what the framework passes
 * around internally; `unwrap()` is what it calls before user code sees a row.
 *
 * The consequence is stated rather than hidden: over a source with nothing to
 * unwrap, an action that wants a model is not available. That is the same
 * capability degradation this layer applies to queries, one level up.
 */
interface RecordContract
{
    /**
     * The row's primary key.
     */
    public function getKey(): int|string;

    /**
     * Read an attribute by dot-path, spanning relations where the source can.
     */
    public function get(string $path): mixed;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * The native row behind this — a `Model` for the Eloquent source, whatever
     * the source holds otherwise, and `null` for a source with no native object
     * to give.
     *
     * The escape hatch that keeps `Model $record` working everywhere it works
     * today. A caller that unwraps is declaring it needs more than the contract
     * offers, which is legitimate and is exactly why the method has a name.
     */
    public function unwrap(): mixed;
}

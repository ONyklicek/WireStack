<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Forms\Runtime\SaveHandler;

/**
 * A field whose value is not a column on the record it belongs to.
 *
 * Three fields already needed this and each got its own line in
 * {@see SaveHandler}: a relationship-backed
 * repeater, a relationship-bound `Tags`, a `MorphToSelect`. All three are the
 * same shape — a field whose *name* is a relation rather than an attribute, so
 * leaving it in the data dehydrates a column that does not exist and fatals.
 *
 * This is that shape, declared instead of enumerated, and it is what lets a
 * field in another package join in. A media field lives in `wire-module-media`,
 * which `wire-forms` neither knows about nor may depend on, so a fourth line in
 * that method was never an option — a contract is.
 *
 * Two things happen for a field that implements it:
 *
 * 1. Its name is removed from the data before the record is written, so nothing
 *    tries to set an attribute that is not there.
 * 2. After the record is saved — and only then, because a new record has no key
 *    until it is — {@see saveAfterRecord()} is called with the field's state.
 *
 * Failing here fails the save. That is deliberate: a gallery that silently did
 * not attach is worse than a save that says it did not finish, because the first
 * is discovered by somebody looking at the page a week later.
 */
interface SavesAfterRecord
{
    /**
     * Persist this field's own value against the record that was just saved.
     *
     * @param  mixed  $state  The field's state, exactly as the form held it.
     */
    public function saveAfterRecord(Model $record, mixed $state): void;
}

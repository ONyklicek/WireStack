<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Marks a component that shapes its own value on the way OUT — from form state
 * into the model. The write-path counterpart of `getStateType()`.
 *
 * Without this seam a component can cast a value inbound but not outbound, so a
 * setter describing how to store something has nowhere to act. See ADR 0021.
 *
 * Implementations must be a **pure function of their arguments**: a host may call
 * this more than once per save (the table dehydrates once without a record to
 * pre-validate outside its transaction, then again with the locked record).
 *
 * A host must always pass the component's original state — never the result of an
 * earlier call. Purity does not imply idempotence under self-composition, so an
 * implementation is free to transform in ways that would break if applied twice.
 */
interface DehydratesState
{
    /**
     * Transform this component's state into the value to persist.
     *
     * The record is passed when the host has one — a table cell always does, a
     * create form does not — so a transform may depend on it.
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed;
}

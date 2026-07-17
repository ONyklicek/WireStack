<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Marks a component that shapes a stored value on the way IN — from the model
 * into the state its widget binds to.
 *
 * Separate from {@see DehydratesState} on purpose: most components need only one
 * direction (a FileUpload stores on save but loads a plain path), and forcing a
 * no-op counterpart on them would be noise. A component that needs both — a
 * date field converting a timezone — implements both, and must: converting only
 * inbound would leave the value shifted when it is written back. See ADR 0021.
 */
interface HydratesState
{
    /**
     * Transform a stored value into this component's state.
     *
     * The record is passed when the host has one.
     */
    public function hydrateState(mixed $value, ?Model $record = null): mixed;
}

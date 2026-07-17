<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use Livewire\Component;
use NyonCode\WireForms\Forms\Form;

/**
 * Contract for actions that support form integration.
 *
 * Implementation is provided by HasModal trait on BaseAction,
 * which offers form(), fillFormUsing(), getFormInstance(),
 * and hasFormModal() methods.
 */
interface HasForm
{
    /**
     * Determine if this action has a form configured.
     */
    public function hasFormInstance(): bool;

    /**
     * Resolve the Form instance for this action's modal.
     *
     * $statePath is the frame's binding base resolved by the host (per modal
     * stack depth); when null the action falls back to the legacy single-slot
     * path for host-less callers.
     */
    public function getFormInstance(?Component $livewire = null, mixed $context = null, ?string $statePath = null): ?Form;
}

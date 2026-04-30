<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use NyonCode\WireForms\Integration\ActionMacros;

/**
 * Contract for actions that support form integration.
 *
 * Implementation is provided by wire-forms package via Action::macro().
 * This interface serves as a marker for actions that can display
 * form fields in their modal dialogs.
 *
 * @see ActionMacros
 */
interface HasForm
{
    /**
     * Get the form schema for this action's modal.
     *
     * @return array<int, mixed>
     */
    public function getFormSchema(): array;

    /**
     * Determine if this action has a form configured.
     */
    public function hasForm(): bool;
}

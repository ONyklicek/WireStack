<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Integration;

use NyonCode\WireCore\Actions\BaseAction;

/**
 * Registers Action macros for form integration.
 *
 * All form methods (form, fillFormUsing, formValidation, getFormInstance,
 * hasFormModal, etc.) are provided natively by HasModal trait on BaseAction.
 * This registration point exists for future forms-package-specific extensions.
 */
final class ActionMacros
{
    public static function register(): void
    {
        if (! class_exists(BaseAction::class)) {
            return;
        }
    }
}

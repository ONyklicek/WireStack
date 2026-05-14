<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Integration;

use NyonCode\WireCore\Actions\BaseAction;

/**
 * Registers Action macros for form integration.
 *
 * Note: Core form methods (form, fillFormUsing, formValidation, getFormInstance,
 * hasFormModal) are provided natively by HasModal trait on BaseAction.
 * This class only registers macros that add forms-package-specific behavior
 * beyond what core provides.
 */
final class ActionMacros
{
    public static function register(): void
    {
        if (! class_exists(BaseAction::class)) {
            return;
        }

        // Core already provides: form(), fillFormUsing(), formValidation(),
        // validationMessages(), validationAttributes(), getFormInstance(),
        // hasFormModal(), getFillFormCallback() via HasModal trait.
        //
        // Register only forms-package-specific extensions here.
    }
}

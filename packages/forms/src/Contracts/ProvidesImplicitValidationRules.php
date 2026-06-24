<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

use NyonCode\WireForms\Concerns\HasFormValidation;
use NyonCode\WireForms\Concerns\HasOptions;

/**
 * A field that contributes implicit validation rules derived from its own configuration.
 *
 * Implemented by option fields whose options come from a PHP enum: the set of valid values
 * is then known, so an `in:` constraint can be added automatically (Filament-style) without
 * the owner restating it. {@see HasOptions} provides the logic;
 * {@see HasFormValidation} merges the result into the field rules.
 */
interface ProvidesImplicitValidationRules
{
    /**
     * Extra rules to merge into the field's validation rule set.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array;
}

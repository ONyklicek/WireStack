<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\FormSavingPayload;

/**
 * The immutable form-configuration surface carried by the form-save plugin
 * hooks ({@see FormSavingPayload} and
 * {@see FormSavedPayload}), owned by
 * wire-core so the payloads never name wire-forms' concrete `FormConfig`.
 *
 * wire-forms' `FormConfig` implements this; a standalone wire-core still
 * compiles and can define plugins against the hooks without the forms package.
 */
interface FormConfigContract
{
    /** The form is creating a new record (no bound model). */
    public function isCreating(): bool;

    /** The form is editing an existing record (a model is bound). */
    public function isEditing(): bool;

    /** A model (class-string or instance) is bound to the form. */
    public function hasModel(): bool;

    /** The bound model class-string or instance, or null when creating. */
    public function getModel(): string|Model|null;
}

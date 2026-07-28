<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Support;

use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Actions\Contracts\ModalFormFactory;

/**
 * Resolves the container-bound {@see ModalFormFactory} and builds modal forms,
 * degrading to `null` when no factory is bound (wire-forms not installed).
 *
 * This is the single point where wire-core reaches for the form runtime, so the
 * "core must not name wire-forms" invariant collapses to one small seam.
 */
final class ModalForms
{
    public static function factory(): ?ModalFormFactory
    {
        return app()->bound(ModalFormFactory::class)
            ? app(ModalFormFactory::class)
            : null;
    }

    /**
     * Build a modal form from a field schema, or `null` when no form runtime is
     * available. Callers already guard a `null` form (the modal degrades to a
     * confirmation/heading dialog).
     *
     * @param  array<int, mixed>  $schema
     */
    public static function make(array $schema = []): ?ModalForm
    {
        return self::factory()?->make($schema);
    }
}

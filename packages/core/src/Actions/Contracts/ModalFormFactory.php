<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Contracts;

use NyonCode\WireCore\Actions\Support\ModalForms;

/**
 * Builds a {@see ModalForm} from a field schema, without wire-core naming the
 * concrete `Form`.
 *
 * wire-forms binds an implementation in its service provider; wire-core resolves
 * it from the container via {@see ModalForms}.
 * When wire-forms is not installed the binding is absent and the resolver
 * degrades to `null`, so a standalone wire-core renders confirmation/infolist
 * modals and simply has no form modal rather than fatally referencing a class it
 * does not ship.
 */
interface ModalFormFactory
{
    /**
     * @param  array<int, mixed>  $schema  Field components for the form.
     */
    public function make(array $schema = []): ModalForm;
}

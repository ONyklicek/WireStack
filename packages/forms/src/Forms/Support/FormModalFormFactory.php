<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Forms\Support;

use NyonCode\WireCore\Actions\Contracts\ModalForm;
use NyonCode\WireCore\Actions\Contracts\ModalFormFactory;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\WireFormsServiceProvider;

/**
 * wire-forms implementation of the core {@see ModalFormFactory} seam: builds a
 * concrete {@see Form} (which implements {@see ModalForm}) from a field schema.
 *
 * Bound in {@see WireFormsServiceProvider} so wire-core can
 * construct action-modal forms without naming `Form`.
 */
final class FormModalFormFactory implements ModalFormFactory
{
    public function make(array $schema = []): ModalForm
    {
        return Form::make()->schema($schema);
    }
}

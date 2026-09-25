<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/**
 * Delete one soft-deleted record for good, past its soft delete.
 *
 * A preset, like {@see DeleteAction}: it confirms and says what it is, and the
 * host supplies what it does with `->action()`.
 *
 * @phpstan-consistent-constructor
 */
class ForceDeleteAction extends Action
{
    public function __construct(string $name = 'forceDelete')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.force_delete_label'))->icon('trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(Trans::get('wire-core::actions.force_delete_heading'))
            ->modalDescription(Trans::get('wire-core::actions.force_delete_description'))
            ->modalSubmitActionLabel(Trans::get('wire-core::actions.delete_submit'));
    }

    public static function make(string $name = 'forceDelete'): static
    {
        return new static($name);
    }
}

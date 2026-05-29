<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/** @phpstan-consistent-constructor */
class ForceDeleteBulkAction extends BulkAction
{
    public function __construct(string $name = 'forceDelete')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.force_delete_bulk_label'))->icon('trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(Trans::get('wire-core::actions.force_delete_bulk_heading'))
            ->modalDescription(Trans::get('wire-core::actions.force_delete_bulk_description'))
            ->modalSubmitActionLabel(Trans::get('wire-core::actions.delete_submit'));
    }

    public static function make(string $name = 'forceDelete'): static
    {
        return new static($name);
    }
}

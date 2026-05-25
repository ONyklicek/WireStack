<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/** @phpstan-consistent-constructor */
class RestoreBulkAction extends BulkAction
{
    public function __construct(string $name = 'restore')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.restore_bulk_label'))->icon('arrow-uturn-left')->color('success')
            ->requiresConfirmation()
            ->modalHeading(Trans::get('wire-core::actions.restore_bulk_heading'))
            ->modalDescription(Trans::get('wire-core::actions.restore_bulk_description'))
            ->modalSubmitActionLabel(Trans::get('wire-core::actions.restore_submit'));
    }

    public static function make(string $name = 'restore'): static
    {
        return new static($name);
    }
}

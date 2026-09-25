<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/**
 * Bring one soft-deleted record back.
 *
 * A preset, like {@see DeleteAction}: it confirms and says what it is, and the
 * host supplies what it does with `->action()`.
 *
 * @phpstan-consistent-constructor
 */
class RestoreAction extends Action
{
    public function __construct(string $name = 'restore')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.restore_label'))->icon('arrow-uturn-left')->color('success')
            ->requiresConfirmation()
            ->modalHeading(Trans::get('wire-core::actions.restore_heading'))
            ->modalDescription(Trans::get('wire-core::actions.restore_description'))
            ->modalSubmitActionLabel(Trans::get('wire-core::actions.restore_submit'));
    }

    public static function make(string $name = 'restore'): static
    {
        return new static($name);
    }
}

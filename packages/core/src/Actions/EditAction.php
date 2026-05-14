<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/** @phpstan-consistent-constructor */
class EditAction extends Action
{
    public function __construct(string $name = 'edit')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.edit_label'))->icon('pencil')->color('primary');
    }

    public static function make(string $name = 'edit'): static
    {
        return new static($name);
    }
}

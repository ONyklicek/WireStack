<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

use NyonCode\WireCore\Core\Support\Trans;

/** @phpstan-consistent-constructor */
class ViewAction extends Action
{
    public function __construct(string $name = 'view')
    {
        parent::__construct($name);
        $this->label(Trans::get('wire-core::actions.view_label'))->icon('eye')->color('gray');
    }

    public static function make(string $name = 'view'): static
    {
        return new static($name);
    }
}

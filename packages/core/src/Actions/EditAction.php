<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

class EditAction extends Action
{
    public function __construct(string $name = 'edit')
    {
        parent::__construct($name);
        $this->label('Upravit')->icon('pencil')->color('primary');
    }

    public static function make(string $name = 'edit'): static
    {
        return new static($name);
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

class ViewAction extends Action
{
    public function __construct(string $name = 'view')
    {
        parent::__construct($name);
        $this->label('Zobrazit')->icon('eye')->color('gray');
    }

    public static function make(string $name = 'view'): static
    {
        return new static($name);
    }
}

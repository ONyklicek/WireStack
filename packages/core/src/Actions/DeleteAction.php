<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

/** @phpstan-consistent-constructor */
class DeleteAction extends Action
{
    public function __construct(string $name = 'delete')
    {
        parent::__construct($name);
        $this->label('Smazat')->icon('trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Smazat záznam')
            ->modalDescription('Opravdu chcete smazat tento záznam? Tato akce je nevratná.')
            ->modalSubmitActionLabel('Smazat');
    }

    public static function make(string $name = 'delete'): static
    {
        return new static($name);
    }
}

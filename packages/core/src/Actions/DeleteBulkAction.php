<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions;

/** @phpstan-consistent-constructor */
class DeleteBulkAction extends BulkAction
{
    public function __construct(string $name = 'delete')
    {
        parent::__construct($name);
        $this->label('Smazat vybrané')->icon('trash')->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Smazat vybrané záznamy')
            ->modalDescription('Opravdu chcete smazat vybrané záznamy? Tato akce je nevratná.')
            ->modalSubmitActionLabel('Smazat');
    }

    public static function make(string $name = 'delete'): static
    {
        return new static($name);
    }
}

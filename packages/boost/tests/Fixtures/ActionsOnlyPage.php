<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/** A page of its own: no table, no form, no infolist — only actions. */
class ActionsOnlyPage extends Component
{
    use WithActions;

    protected function actions(): array
    {
        return [
            Action::make('backup')
                ->color('blue')
                ->form([TextInput::make('bucket')])
                ->action(fn () => null),
        ];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

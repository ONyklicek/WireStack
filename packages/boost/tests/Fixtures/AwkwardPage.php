<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Concerns\WithActions;
use RuntimeException;

/**
 * Header actions that cannot be listed, and a record property that takes only
 * an integer — the two ways a page can refuse what the validator hands it.
 */
class AwkwardPage extends Component
{
    use WithActions;

    public ?int $record = null;

    /** @return array<int, Action> */
    public function getHeaderActions(): array
    {
        throw new RuntimeException('header needs the request');
    }

    protected function actions(): array
    {
        return [Action::make('ping')->action(fn () => null)];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

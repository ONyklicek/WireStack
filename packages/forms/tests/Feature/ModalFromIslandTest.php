<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * A standalone host that draws its modals in an `action-modals` island and has
 * a panel island of its own: a button in the panel that opens a modal from a
 * method — `mountAction()` after a lookup, not a button for the action itself —
 * answers with the panel AND the modal. Livewire targets the panel on its own,
 * which used to send the panel alone and leave the mounted modal undrawn.
 */
class MfiFormsHost extends Component
{
    use WithActions;

    public function openNote(): void
    {
        $this->mountAction('writeNote');
    }

    protected function actions(): array
    {
        return [Action::make('writeNote')->modalHeading('Write a note')->action(fn () => null)];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @island('panel', always: true)<button wire:click="openNote">Panel</button>@endisland
                @island('action-modals', always: true)<x-wire-actions::modal-host :component="$__livewire" />@endisland
            </div>
            BLADE;
    }
}

/** A host with islands but none for its modals: the hook has nothing to add. */
class MfiFormsHostWithoutModalIsland extends MfiFormsHost
{
    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @island('panel', always: true)<button wire:click="openNote">Panel</button>@endisland
                <x-wire-actions::modal-host :component="$this" />
            </div>
            BLADE;
    }
}

beforeEach(fn () => config()->set('app.key', 'base64:'.base64_encode(random_bytes(32))));

it('answers a panel button that mounts a modal with the panel and the modal', function (string $host, int $fragments, bool $drawsModal) {
    $response = test()->withHeader('X-Livewire', 'true')->postJson(
        app('livewire')->getUpdateUri(),
        ['components' => [[
            'snapshot' => json_encode(Livewire::test($host)->snapshot),
            'updates' => [],
            'calls' => [[
                'method' => 'openNote',
                'params' => [],
                'path' => '',
                'id' => (string) random_int(1, 99999),
                'metadata' => ['island' => ['name' => 'panel', 'mode' => 'morph']],
            ]],
        ]]],
    );

    $effects = $response->assertOk()->json('components.0.effects');

    expect($effects['islandFragments'])->toHaveCount($fragments)
        ->and(str_contains(implode('', $effects['islandFragments']), 'Write a note'))->toBe($drawsModal);
})->with([
    'modals in their own island' => [MfiFormsHost::class, 2, true],
    'no island for the modals' => [MfiFormsHostWithoutModalIsland::class, 1, false],
]);

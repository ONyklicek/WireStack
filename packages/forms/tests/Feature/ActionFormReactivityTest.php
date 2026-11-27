<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;

/*
 * A reactive field in an action modal on a host that is not a table.
 *
 * The table always ran a modal field's `afterStateUpdated()`: its update hook
 * mapped a `tableState.modal.actions.*` path to the modal's form. A page has no
 * table, and `WithActions` had no hook at all — so a `->live()` select in a
 * page's header action changed its value and nothing else, with no error to say
 * so. Both hosts now answer through one mapping, `InteractsWithActionForms`.
 */

class AfrHost extends Component
{
    use WithActions;

    /** @var array<int, string> */
    public array $log = [];

    protected function actions(): array
    {
        return [
            Action::make('backup')
                ->form([
                    Select::make('provider')
                        ->options(['s3' => 'S3', 'b2' => 'B2'])
                        ->live()
                        ->afterStateUpdated(function ($state, $old, $set): void {
                            $this->log[] = "provider:{$old}->{$state}";
                            $set('endpoint', $state === 's3' ? 's3.amazonaws.com' : 'backblazeb2.com');
                        }),
                    TextInput::make('endpoint'),
                ])
                ->action(fn () => null),

            Action::make('order')
                ->form([
                    Repeater::make('items')
                        ->schema([
                            TextInput::make('qty')->live()
                                ->afterStateUpdated(fn ($state) => $this->log[] = "qty:{$state}"),
                        ])
                        ->default([['qty' => 1]]),
                ])
                ->action(fn () => null),

            Action::make('invite')
                ->form([TextInput::make('email')->rules(['email'])->validateLive()])
                ->action(fn () => null),

            // A parent that stays live behind a child it opens.
            Action::make('parent')
                ->form([
                    TextInput::make('repository')->live()
                        ->afterStateUpdated(function ($set): void {
                            $this->log[] = 'repository';
                            $set('branch', null);
                        }),
                    TextInput::make('branch'),
                ])
                ->action(fn () => null),
            Action::make('child')
                ->form([
                    TextInput::make('note')->live()
                        ->afterStateUpdated(fn () => $this->log[] = 'note'),
                ])
                ->action(fn () => null),

            // A halt asks with a form; its fields bind to their own bag.
            Action::make('archive')
                ->action(function (bool $confirmed, callable $halt) {
                    if (! $confirmed) {
                        return $halt()
                            ->heading('Why?')
                            ->form([TextInput::make('reason')->rules(['min:3'])->validateLive()]);
                    }
                }),
        ];
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-wire-actions::modal-host :component="$this" />
            </div>
        BLADE;
    }
}

it('runs afterStateUpdated for a field in an action modal, with the old value', function () {
    Livewire::test(AfrHost::class)
        ->call('mountAction', 'backup')
        ->set('mountedActions.0.data.provider', 's3')
        ->assertSet('log', ['provider:->s3'])
        ->assertSet('mountedActions.0.data.endpoint', 's3.amazonaws.com')
        ->set('mountedActions.0.data.provider', 'b2')
        ->assertSet('log', ['provider:->s3', 'provider:s3->b2'])
        ->assertSet('mountedActions.0.data.endpoint', 'backblazeb2.com');
});

it('runs afterStateUpdated for a field inside a repeater item in an action modal', function () {
    $component = Livewire::test(AfrHost::class)->call('mountAction', 'order');

    $key = array_key_first($component->get('mountedActions.0.data.items'));

    $component->set("mountedActions.0.data.items.{$key}.qty", 5)
        ->assertSet('log', ['qty:5']);
});

it('validates a live field in an action modal as it changes', function () {
    Livewire::test(AfrHost::class)
        ->call('mountAction', 'invite')
        ->set('mountedActions.0.data.email', 'not-an-address')
        ->assertHasErrors('mountedActions.0.data.email')
        ->set('mountedActions.0.data.email', 'ada@example.com')
        ->assertHasNoErrors('mountedActions.0.data.email');
});

it('answers the frame that owns the path, not only the top one', function () {
    Livewire::test(AfrHost::class)
        ->call('mountAction', 'parent')
        ->set('mountedActions.0.data.branch', 'main')
        ->call('mountAction', 'child')
        ->set('mountedActions.1.data.note', 'x')
        ->assertSet('log', ['note'])
        // The parent's field, written while the child is on top, still reacts.
        ->set('mountedActions.0.data.repository', 'acme/site')
        ->assertSet('log', ['note', 'repository'])
        ->assertSet('mountedActions.0.data.branch', null);
});

it('validates a live field in a halt form', function () {
    Livewire::test(AfrHost::class)
        ->call('mountAction', 'archive')
        ->assertSet('mountedHalt.show', true)
        ->set('actionModalHaltData.reason', 'x')
        ->assertHasErrors('actionModalHaltData.reason');
});

it('leaves a write outside any modal alone', function () {
    Livewire::test(AfrHost::class)
        ->call('mountAction', 'backup')
        ->set('log', ['kept'])
        ->assertSet('log', ['kept'])
        ->assertSet('mountedActions.0.data.endpoint', null);
});

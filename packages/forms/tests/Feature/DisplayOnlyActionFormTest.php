<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Concerns\WithActions;

/*
| A modal form made only of display fields declares no rules. Livewire reads
| `validate([])` as "use the component's rules()", so submitting one threw
| MissingRulesException on a host with none, and on a host with rules of its own
| validated properties the modal never showed.
*/

class DisplayOnlyActionFormHost extends Component
{
    use WithActions;

    public string $log = '';

    protected function actions(): array
    {
        return [
            Action::make('shareLink')
                ->form([
                    Html::make('link')->content('<a href="https://example.test/sign">Sign</a>'),
                    Placeholder::make('hint')->content('Copy the link or send it.'),
                ])
                ->action(fn (array $data) => $this->log = 'sent:'.count($data)),
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

class DisplayOnlyActionFormHostWithRules extends DisplayOnlyActionFormHost
{
    /** Unrelated to the modal — and left empty, so validating it would fail. */
    public string $title = '';

    /** @return array<string, string> */
    protected function rules(): array
    {
        return ['title' => 'required'];
    }
}

it('runs an action whose modal form has only display fields', function (string $host) {
    Livewire::test($host)
        ->call('mountAction', 'shareLink')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertSet('log', 'sent:0');
})->with([
    'host without rules' => DisplayOnlyActionFormHost::class,
    'host with rules of its own' => DisplayOnlyActionFormHostWithRules::class,
]);

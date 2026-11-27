<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Contracts\HasStateAccessors;
use NyonCode\WireForms\Components\Display\Alert;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\Display\Placeholder;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Concerns\WithActions;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Display components read form state the way fields do.
 *
 * A Placeholder, an Html block and an Alert are not fields, and until they took
 * the field's state accessors a `content(fn ($get) => …)` on one of them died on
 * "Unable to resolve dependency [Parameter #0 $get]" — a container message that
 * names neither the component nor the cause.
 */

class DsaForm extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['name' => '', 'notify' => false, 'lines' => [['qty' => 2]]];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            TextInput::make('name')->live(),
            TextInput::make('notify')->live(),
            Placeholder::make('preview')->content(fn ($get) => 'Preview: '.$get('name')),
            Placeholder::make('note')->content('Notifications are on')->visible(fn ($get) => (bool) $get('notify')),
            Html::make('markup')->content(fn ($get) => '<b>'.e((string) $get('name')).'</b>'),
            Alert::make('warning')->content(fn ($get) => 'Alert for '.$get('name')),
            Repeater::make('lines')->schema([
                TextInput::make('qty')->live(),
                Placeholder::make('line_total')->content(fn ($get) => 'Line total: '.((int) $get('qty') * 10)),
            ]),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class DsaActions extends Component
{
    use WithActions;

    protected function actions(): array
    {
        return [
            Action::make('rename')
                ->form([
                    TextInput::make('title')->live(),
                    Placeholder::make('preview')->content(fn ($get) => 'Will be called '.$get('title')),
                ])
                ->action(fn () => null),
        ];
    }

    public function render(): string
    {
        return '<div><x-wire-actions::modal-host :component="$this" /></div>';
    }
}

it('gives every display component the state accessors', function () {
    expect(Placeholder::make('p'))->toBeInstanceOf(HasStateAccessors::class)
        ->and(Html::make('h'))->toBeInstanceOf(HasStateAccessors::class)
        ->and(Alert::make('a'))->toBeInstanceOf(HasStateAccessors::class);
});

it('resolves $get in display content against the form bag', function () {
    Livewire::test(DsaForm::class)
        ->set('data.name', 'Ada')
        ->assertSee('Preview: Ada')
        ->assertSeeHtml('<b>Ada</b>')
        ->assertSee('Alert for Ada');
});

it('resolves $get in a display component visibility condition', function () {
    Livewire::test(DsaForm::class)
        ->assertDontSee('Notifications are on')
        ->set('data.notify', true)
        ->assertSee('Notifications are on');
});

it('resolves $get against the repeater item a display component sits in', function () {
    $component = Livewire::test(DsaForm::class);

    $key = array_key_first($component->get('data.lines'));

    $component->assertSee('Line total: 20')
        ->set("data.lines.{$key}.qty", 3)
        ->assertSee('Line total: 30');
});

it('resolves $get in display content inside an action modal', function () {
    Livewire::test(DsaActions::class)
        ->call('mountAction', 'rename')
        ->set('mountedActions.0.data.title', 'Budget')
        ->assertSee('Will be called Budget');
});

it('answers a display component outside any form with defaults, not an error', function () {
    $accessors = Placeholder::make('p')->getStateAccessors();

    expect(($accessors['get'])('anything', 'fallback'))->toBe('fallback')
        ->and($accessors['state'])->toBeNull();
});

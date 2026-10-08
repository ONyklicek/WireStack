<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * A new repeater row starts with what its fields declare as `->default()`.
 * It used to start empty, so a required field with a default — a direction
 * select, say — arrived blank and the save failed on it; a host had to override
 * `addRepeaterItem()` to put the defaults in by hand.
 *
 * Defaults only, never the structural blanks a whole form self-seeds with: a
 * relationship repeater's row becomes a record, and a key set to null there
 * would override the column's database default.
 */

enum RnrDirection: string
{
    case Up = 'up';
    case Down = 'down';
}

class RnrBandsComponent extends Component
{
    use WithForms;

    public array $data = ['bands' => [], 'tags' => []];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Repeater::make('bands')->schema([
                TextInput::make('label')->default('band'),
                // No default: the row carries no key for it.
                TextInput::make('step'),
                Grid::make()->columns(2)->schema([
                    Select::make('mode')->options(RnrDirection::class)->default(RnrDirection::Up),
                ]),
            ]),
            Repeater::make('tags')->schema([TextInput::make('name')]),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('starts a new row with its fields\' declared defaults, through layouts, enums as their value', function () {
    $component = Livewire::test(RnrBandsComponent::class)->call('addRepeaterItem', 'data.bands');

    expect($component->get('data')['bands'])->toBe([['label' => 'band', 'mode' => 'up']]);
});

it('starts a row with nothing when its fields declare nothing, as before', function () {
    $component = Livewire::test(RnrBandsComponent::class)->call('addRepeaterItem', 'data.tags');

    expect($component->get('data')['tags'])->toBe([[]]);
});

it('starts an empty row for a path no form of the host knows', function () {
    $component = Livewire::test(RnrBandsComponent::class)->call('addRepeaterItem', 'data.unknown');

    expect($component->get('data')['unknown'])->toBe([[]]);
});

it('answers null for a path that holds no repeater', function () {
    $form = Livewire::test(RnrBandsComponent::class)->instance()->form;

    expect($form->newRepeaterItemState('data.bands'))->toBe(['label' => 'band', 'mode' => 'up'])
        ->and($form->newRepeaterItemState('data.nope'))->toBeNull()
        // Under a repeater, but not an item index: no repeater there.
        ->and($form->newRepeaterItemState('data.bands.nope'))->toBeNull();
});

class RnrNestedComponent extends Component
{
    use WithForms;

    public array $data = ['contacts' => [['name' => 'Alpha', 'phones' => []]]];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Repeater::make('contacts')->schema([
                TextInput::make('name'),
                Repeater::make('phones')->schema([TextInput::make('kind')->default('mobile')]),
            ]),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('starts a row of a repeater nested in another\'s row with its defaults too', function () {
    $component = Livewire::test(RnrNestedComponent::class)->call('addRepeaterItem', 'data.contacts.0.phones');

    expect($component->get('data')['contacts'][0]['phones'])->toBe([['kind' => 'mobile']]);
});

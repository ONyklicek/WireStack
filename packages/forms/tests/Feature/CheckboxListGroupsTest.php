<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * grouped()/groups() were dead setters: the list always rendered one flat grid,
 * so a grouped configuration looked exactly like an ungrouped one.
 *
 * Rendered through a real host — the view reads Livewire's $_instance, so it
 * cannot render standalone.
 */

class ChecklistGroupsHost extends Component
{
    use WithForms;

    public array $data = ['food' => []];

    public string $mode = 'groups';

    public function form(Form $form): Form
    {
        $field = match ($this->mode) {
            'groups' => CheckboxList::make('food')->groups([
                'Fruit' => ['apple' => 'Apple', 'pear' => 'Pear'],
                'Veg' => ['leek' => 'Leek'],
            ]),
            // grouped() alone says "group these" without saying by what.
            'grouped-only' => CheckboxList::make('food')->options(['apple' => 'Apple'])->grouped(),
            default => CheckboxList::make('food')->options(['apple' => 'Apple']),
        };

        return $form->statePath('data')->schema([$field]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('renders a heading per group', function () {
    Livewire::test(ChecklistGroupsHost::class)
        ->assertSee('Fruit')
        ->assertSee('Veg')
        ->assertSee('form-checklist-data.food-apple', false)
        ->assertSee('form-checklist-data.food-leek', false)
        ->assertSee('form-checklist-data.food-group', false);
});

it('renders a flat list when no groups are configured', function () {
    Livewire::test(ChecklistGroupsHost::class, ['mode' => 'flat'])
        ->assertDontSee('form-checklist-data.food-group', false)
        ->assertSee('form-checklist-data.food-apple', false);
});

it('stays flat when grouped() is set without a group map', function () {
    Livewire::test(ChecklistGroupsHost::class, ['mode' => 'grouped-only'])
        ->assertDontSee('form-checklist-data.food-group', false)
        ->assertSee('form-checklist-data.food-apple', false);
});

it('turns grouping on implicitly when a group map is given', function () {
    expect(CheckboxList::make('food')->groups(['Fruit' => ['apple' => 'Apple']])->isGrouped())->toBeTrue();
});

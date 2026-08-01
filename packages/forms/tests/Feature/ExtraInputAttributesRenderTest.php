<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\TimePicker;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * The half of extraInputAttributes() the unit test cannot reach: fields that
 * bind through @entangle need a real Livewire request to render at all.
 */

class ExtraInputAttrHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Toggle::make('active')->extraInputAttributes(['data-probe' => 'toggle']),
                Slider::make('volume')->extraInputAttributes(['data-probe' => 'slider']),
                ColorPicker::make('brand')->extraInputAttributes(['data-probe' => 'color']),
                DateTimePicker::make('due')->extraInputAttributes(['data-probe' => 'datetime']),
                TimePicker::make('at')->extraInputAttributes(['data-probe' => 'time']),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('renders the attributes on every entangle-bound field', function (string $probe) {
    expect(Livewire::test(ExtraInputAttrHost::class)->html())
        ->toContain('data-probe="'.$probe.'"');
})->with(['toggle', 'slider', 'color', 'datetime', 'time']);

it('puts each field’s attributes on that field only', function () {
    $html = Livewire::test(ExtraInputAttrHost::class)->html();

    // One occurrence each: no field leaks its attributes onto a sibling.
    foreach (['toggle', 'slider', 'color', 'datetime', 'time'] as $probe) {
        expect(substr_count($html, 'data-probe="'.$probe.'"'))->toBe(1, "probe {$probe}");
    }
});

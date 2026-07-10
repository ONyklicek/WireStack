<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\ColorPicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\FileUpload;
use NyonCode\WireForms\Components\OtpInput;
use NyonCode\WireForms\Components\Radio;
use NyonCode\WireForms\Components\Rating;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Slider;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Guards the stable user-level selectors on form fields (data-testid /
 * accessible names) so Pest Browser Testing can target them at the user level.
 */
class FormHooksComponent extends Component
{
    /** @var array<string, mixed> */
    public array $data = ['name' => '', 'active' => false, 'role' => null, 'files' => [], 'rows' => []];

    use WithForms;

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            TextInput::make('name'),
            Toggle::make('active'),
            Select::make('role')->options(['admin' => 'Admin', 'editor' => 'Editor'])->searchable(),
            FileUpload::make('files')->multiple(),
            Repeater::make('rows')->schema([TextInput::make('label')]),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('gives every field a stable container hook', function () {
    $html = Livewire::test(FormHooksComponent::class)->html();

    expect($html)
        ->toContain('data-testid="form-field-data.name"')       // every field wrapper
        ->toContain('data-testid="form-field-data.active"')
        ->toContain('data-testid="form-field-data.role"')
        ->toContain('data-field="data.name"');
});

it('tags the interactive controls per field type', function () {
    $html = Livewire::test(FormHooksComponent::class)->html();

    expect($html)
        ->toContain('data-testid="form-toggle-data.active"')            // toggle switch
        ->toContain('data-testid="select-trigger"')                    // searchable select combobox
        ->toContain('data-testid="select-search"')
        ->toContain('data-testid="form-repeater-data.rows-add"');      // repeater add
});

// A form exercising the granular "micro" controls.
class FormMicroHooksComponent extends Component
{
    /** @var array<string, mixed> */
    public array $data = ['when' => null, 'shade' => '#ff0000', 'stars' => 0, 'code' => '', 'plan' => null, 'agree' => false, 'level' => 5];

    use WithForms;

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            DateTimePicker::make('when')->withSeconds(),
            ColorPicker::make('shade')->swatches(['#ff0000', '#00ff00']),
            Rating::make('stars')->max(5),
            OtpInput::make('code')->length(4),
            Radio::make('plan')->options(['free' => 'Free', 'pro' => 'Pro']),
            Checkbox::make('agree'),
            Slider::make('level'),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('tags the granular micro-controls (calendar, swatches, stars, otp, radio, checkbox, slider)', function () {
    $html = Livewire::test(FormMicroHooksComponent::class)->html();

    expect($html)
        ->toContain('data-testid="form-datetime-data.when-prev-month"')     // calendar nav
        ->toContain('data-testid="form-datetime-data.when-hours-up"')       // time stepper
        ->toContain('data-testid="form-datetime-data.when-clear"')
        ->toContain('data-testid="form-color-data.shade-swatch-#ff0000"')   // color swatch
        ->toContain('data-testid="form-rating-data.stars-star-1"')          // rating star
        ->toContain('data-testid="form-otp-data.code-0"')                   // otp digit
        ->toContain('data-testid="form-radio-data.plan-free"')              // radio option
        ->toContain('data-testid="form-checkbox-data.agree"')               // checkbox
        ->toContain('data-testid="form-slider-data.level"');                // slider
});

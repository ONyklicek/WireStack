<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\PhoneInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\WireFormsServiceProvider;

/**
 * The field entangles rather than binding `wire:model`: the select and the
 * number input are two controls over one state key, and only the controller
 * knows how they compose. `@entangle` compiles to code that reads
 * `$__livewire`, so this has to render on a real host.
 */
class PhoneInputHost extends Component
{
    /** @var array<string, mixed> */
    public array $data = [];

    use WithForms;

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            PhoneInput::make('phone')->countries(['CZ', 'SK']),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('renders the country select beside the number input, over one entangled value', function () {
    $html = Livewire::test(PhoneInputHost::class)->html();

    expect($html)->toContain('wirePhoneInput(')
        ->toContain('.entangle(')
        ->toContain('data-testid="form-phone-data.phone-country"')
        ->toContain('data-testid="form-phone-data.phone-number"')
        // The flags are resolved in PHP; the browser never rebuilds one.
        ->toContain('🇨🇿 +420')
        ->toContain('🇸🇰 +421')
        // Only the offered countries reach the markup.
        ->not->toContain('+49');
});

it('registers the controller in the shipped bundle', function () {
    // The view asks for `wirePhoneInput`; the bundle the package actually ships
    // has to define it. Asserting against dist rather than the source is what
    // catches a `resources/js` edit that was never rebuilt
    // (`npm run build:forms-assets`).
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-fields.js';

    expect(is_file($bundle))->toBeTrue()
        ->and((string) file_get_contents($bundle))->toContain('wirePhoneInput');
});

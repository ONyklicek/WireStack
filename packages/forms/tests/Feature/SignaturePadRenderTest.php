<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\SignaturePad;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\WireFormsServiceProvider;

/**
 * The pad entangles its data URI rather than binding `wire:model` — the canvas
 * owns the value between strokes — so it can only render on a real host.
 */
class SignaturePadHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            SignaturePad::make('signature')->height(220)->penColor('#1d4ed8'),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

it('renders a canvas configured from PHP', function () {
    $html = Livewire::test(SignaturePadHost::class)->html();

    expect($html)->toContain('wireSignaturePad(')
        ->toContain('.entangle(')
        ->toContain('data-testid="form-signature-data.signature-canvas"')
        ->toContain('data-testid="form-signature-data.signature-clear"')
        ->toContain('height: 220px')
        ->toContain('#1d4ed8')
        // touch-none is what stops a finger from scrolling the page mid-stroke.
        ->toContain('touch-none');
});

it('registers the controller in the shipped bundle', function () {
    $bundle = WireFormsServiceProvider::ASSETS_PATH.'/wire-forms-fields.js';

    expect(is_file($bundle))->toBeTrue()
        ->and((string) file_get_contents($bundle))->toContain('wireSignaturePad');
});

<?php

declare(strict_types=1);

use Livewire\Component;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireForms\Exceptions\FormConfigurationException;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Declares a form it never defines — the shape a typo in an explicit
 * getForms() takes.
 */
class MisdeclaredFormHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $profileData = [];

    public function profileForm(Form $form): Form
    {
        return $form->statePath('profileData')->schema([]);
    }

    /**
     * @return array<string>
     */
    protected function getForms(): array
    {
        return ['profileForm', 'billingForm'];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

it('names the method it looked for when a declared form has no method', function () {
    // The message must name the missing method: getting "billingForm" back is
    // what tells the author which declaration is wrong.
    try {
        (new MisdeclaredFormHost)->billingForm;
        $this->fail('Expected a declared-but-undefined form to be rejected.');
    } catch (FormConfigurationException $e) {
        expect($e->getMessage())->toContain('billingForm()')
            ->toContain(MisdeclaredFormHost::class)
            ->and($e)->toBeInstanceOf(WireException::class)
            // The SPL base callers have always been able to catch.
            ->and($e)->toBeInstanceOf(InvalidArgumentException::class);
    }
});

it('resolves a declared form that does exist', function () {
    expect((new MisdeclaredFormHost)->profileForm)->toBeInstanceOf(Form::class);
});

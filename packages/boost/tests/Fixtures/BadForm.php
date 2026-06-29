<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * A deliberately malformed form whose schema contains a non-component value,
 * used to exercise the reflector's flatten guard.
 */
class BadForm extends Component
{
    use WithForms;

    public function form(Form $form): Form
    {
        return $form->schema(['not-a-component']);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

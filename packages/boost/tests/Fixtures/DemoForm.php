<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Tests\Fixtures;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class DemoForm extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Section::make('Profile')->schema([
                TextInput::make('name')->required(),
                Select::make('role'),
            ]),
            Toggle::make('active'),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class FormPreview extends Component
{
    use WithForms;

    public string $variant = 'overview';

    public array $data = [];

    public array $contacts = [];

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
        $contacts = $variant === 'repeater'
            ? [
                ['label' => 'Support', 'value' => 'support@example.com'],
                ['label' => 'Billing', 'value' => '+420 777 555 222'],
                ['label' => 'Slack', 'value' => '@amelia.stone'],
            ]
            : [
                ['label' => 'Support', 'value' => 'support@example.com'],
            ];

        $this->contacts = $contacts;

        $this->data = [
            'name' => 'Amelia Stone',
            'email' => 'amelia@example.com',
            'role' => 'admin',
            'is_active' => true,
            'bio' => 'Owns product configuration, release notes, and customer rollouts.',
        ];
    }

    public function form(Form $form): Form
    {
        return $this->variant === 'repeater'
            ? $this->buildRepeaterForm($form)
            : $this->buildOverviewForm($form);
    }

    public function render()
    {
        return view('livewire.previews.form-preview');
    }

    protected function buildOverviewForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('Workspace profile')
                    ->description('Standalone form rendering with layout components, validation-ready inputs, and repeaters.')
                    ->icon('user')
                    ->schema([
                        Grid::make()
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Full name')
                                    ->required(),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required(),
                                Select::make('role')
                                    ->label('Role')
                                    ->options([
                                        'admin' => 'Administrator',
                                        'manager' => 'Manager',
                                        'editor' => 'Editor',
                                        'viewer' => 'Viewer',
                                    ])
                                    ->required(),
                                Toggle::make('is_active')
                                    ->label('Account active')
                                    ->onLabel('Enabled')
                                    ->offLabel('Paused'),
                            ]),
                        Textarea::make('bio')
                            ->label('Internal note')
                            ->rows(3),
                    ]),
                Section::make('Contacts')
                    ->description('Nested array state with add, remove, and reorder controls.')
                    ->compact()
                    ->schema([
                        Repeater::make('contacts')
                            ->schema($this->contactSchema())
                            ->reorderable()
                            ->minItems(1)
                            ->addButtonLabel('Add contact'),
                    ]),
            ]);
    }

    protected function buildRepeaterForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Contacts')
                    ->description('Nested array state with add, remove, and reorder controls.')
                    ->schema([
                        Repeater::make('contacts')
                            ->schema($this->contactSchema())
                            ->reorderable()
                            ->minItems(1)
                            ->addButtonLabel('Add contact'),
                    ]),
            ]);
    }

    /**
     * @return array<int, Grid>
     */
    protected function contactSchema(): array
    {
        return [
            Grid::make()
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->required(),
                    TextInput::make('value')
                        ->label('Value')
                        ->required(),
                ]),
        ];
    }
}

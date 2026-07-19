<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use Workbench\App\Enums\PreviewStatus;

class FormPreview extends Component
{
    use WithForms;

    public string $variant = 'overview';

    public array $data = [];

    public array $contacts = [];

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;

        if ($variant === 'enum-defaults') {
            // A create-mode host: fill() with no record is what seeds each
            // field's ->default() into the bound state.
            $this->data = [];
            $this->form->fill([]);

            return;
        }

        if ($variant === 'default-on-null') {
            // An edit-mode host: the "record" carries an intentional null for
            // every field. Only the ->defaultOnNull() field should take its
            // default; the plain-default field must keep the null.
            $this->data = [];
            $this->form->fill(['status' => null, 'kind' => null, 'qty' => null]);

            return;
        }

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

        if ($variant === 'wizard-live') {
            $this->data = [
                'name' => '',
                'category' => null,
                'category_note' => '',
                'email' => '',
                'wants_extras' => false,
                'extra_note' => '',
            ];
        }
    }

    /** @var array<string, string> In-memory option store for the create-option flow. */
    public array $categoryOptions = ['news' => 'News', 'sport' => 'Sport'];

    public function form(Form $form): Form
    {
        return match ($this->variant) {
            'enum-defaults' => $this->buildEnumDefaultsForm($form),
            'default-on-null' => $this->buildDefaultOnNullForm($form),
            'repeater' => $this->buildRepeaterForm($form),
            'tabs' => $this->buildTabsForm($form),
            'wizard' => $this->buildWizardForm($form),
            'wizard-live' => $this->buildWizardLiveForm($form),
            default => $this->buildOverviewForm($form),
        };
    }

    /**
     * Create-mode defaults: an enum-sourced select seeded with an enum-instance
     * ->default(), a clearable enum select (empty value), and a numeric input
     * whose ->default() must reach the rendered value attribute.
     */
    protected function buildEnumDefaultsForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('status')
                    ->label('Status')
                    ->options(PreviewStatus::class)
                    ->default(PreviewStatus::Draft),
                Select::make('priority')
                    ->label('Priority (clearable)')
                    ->options(PreviewStatus::class)
                    ->placeholder('No priority'),
                TextInput::make('qty')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1),
            ]);
    }

    /**
     * Edit mode with an all-null record: only the ->defaultOnNull() fields
     * (status, qty) resurrect their default; the plain-default field (kind)
     * keeps the record's null.
     */
    protected function buildDefaultOnNullForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('status')
                    ->label('Status (defaultOnNull)')
                    ->options(PreviewStatus::class)
                    ->default(PreviewStatus::Draft)
                    ->defaultOnNull(),
                Select::make('kind')
                    ->label('Kind (plain default)')
                    ->options(PreviewStatus::class)
                    ->default(PreviewStatus::Published)
                    ->placeholder('No kind'),
                TextInput::make('qty')
                    ->label('Quantity (defaultOnNull)')
                    ->numeric()
                    ->default(1)
                    ->defaultOnNull(),
            ]);
    }

    /** Validate the current state and surface the outcome for the driver to read. */
    public function submitPreview(): void
    {
        $this->form->validate();
        $this->dispatch('preview-validated');
    }

    /**
     * Exercises the reactive wizard stack end-to-end: per-step server
     * validation on Next, a live() select with a visibleWhen sibling and a
     * create-option modal, and a live toggle revealing a whole extra step.
     */
    protected function buildWizardLiveForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Wizard::make('signup')->schema([
                    Step::make('Account')->description('Who is signing up')->schema([
                        TextInput::make('name')->label('Full name')->rules(['required']),
                        Select::make('category')
                            ->label('Category')
                            ->live()
                            ->options(fn () => $this->categoryOptions)
                            ->getOptionLabelUsing(fn ($value) => $this->categoryOptions[$value] ?? null)
                            ->createOptionForm([TextInput::make('label')->label('Label')->rules(['required'])])
                            ->createOptionUsing(function (array $data) {
                                $key = 'c'.(count($this->categoryOptions) + 1);
                                $this->categoryOptions[$key] = (string) $data['label'];

                                return $key;
                            }),
                        TextInput::make('category_note')
                            ->label('Why sport?')
                            ->visibleWhen('category', 'sport'),
                    ]),
                    Step::make('Contact')->description('How to reach you')->schema([
                        TextInput::make('email')->label('Email address')->rules(['required', 'email']),
                        Toggle::make('wants_extras')->label('Add extras step')->live(),
                    ]),
                    Step::make('Extras')
                        ->description('Only when requested')
                        ->visible(fn ($get) => (bool) $get('wants_extras'))
                        ->schema([
                            TextInput::make('extra_note')->label('Extra note')->rules(['required']),
                        ]),
                    Step::make('Review')->schema([
                        Textarea::make('bio')->label('Summary')->rows(3),
                    ]),
                ]),
            ]);
    }

    protected function buildTabsForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Tabs::make()->schema([
                    Tab::make('Profile')->icon('user')->columns(2)->schema([
                        TextInput::make('name')->label('Full name')->required(),
                        TextInput::make('email')->label('Email address')->required(),
                    ]),
                    Tab::make('Preferences')->schema([
                        Toggle::make('is_active')->label('Account active'),
                    ]),
                    Tab::make('Notes & long tab label')->schema([
                        Textarea::make('bio')->label('Internal note')->rows(3),
                    ]),
                ]),
            ]);
    }

    protected function buildWizardForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Wizard::make()->schema([
                    Step::make('Account')->description('Login details')->icon('user')->schema([
                        TextInput::make('name')->label('Full name')->required(),
                    ]),
                    Step::make('Contact')->description('How to reach you')->schema([
                        TextInput::make('email')->label('Email address')->required(),
                    ]),
                    Step::make('Review & confirm')->schema([
                        Textarea::make('bio')->label('Summary')->rows(3),
                    ]),
                ]),
            ]);
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
        // Collapsible so the morph-safe collapse state (byte-stable x-data) is
        // exercisable in a browser: collapse a row, add a row, the row stays collapsed.
        return $form
            ->schema([
                Section::make('Contacts')
                    ->description('Nested array state with add, remove, and reorder controls.')
                    ->schema([
                        Repeater::make('contacts')
                            ->schema($this->contactSchema())
                            ->reorderable()
                            ->collapsible()
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

<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\Block;
use NyonCode\WireForms\Components\Builder;
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

    public array $lines = [];

    public array $content = [];

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

        if ($variant === 'repeater-table') {
            $this->lines = [
                ['description' => 'Consulting', 'quantity' => '4', 'amount' => '1200'],
                ['description' => 'Hosting', 'quantity' => '1', 'amount' => '300'],
            ];
        }

        if ($variant === 'builder') {
            $this->content = [
                ['type' => 'heading', 'data' => ['text' => 'Release notes']],
                ['type' => 'paragraph', 'data' => ['body' => 'Everything that shipped this week.']],
            ];
        }

        $this->data = [
            'name' => 'Amelia Stone',
            'email' => 'amelia@example.com',
            'role' => 'admin',
            'is_active' => true,
            'bio' => 'Owns product configuration, release notes, and customer rollouts.',
        ];

        if ($variant === 'option-wizard') {
            $this->data = ['category' => null];
        }

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
            'repeater-table' => $this->buildRepeaterTableForm($form),
            'builder' => $this->buildBuilderForm($form),
            'tabs' => $this->buildTabsForm($form),
            'wizard' => $this->buildWizardForm($form),
            'wizard-live' => $this->buildWizardLiveForm($form),
            'option-wizard' => $this->buildOptionWizardForm($form),
            'field-partials' => $this->buildFieldPartialsForm($form),
            default => $this->buildOverviewForm($form),
        };
    }

    /**
     * `fieldPartials()` under a live field: what a keystroke commit answers with.
     *
     * Four shapes on one form, because they take four different routes:
     *  - `note` is live and nothing reads it, so committing it moves no markup
     *    anywhere — a TextInput renders no `value` attribute — and the answer is
     *    nothing at all;
     *  - `name` is live and `summary` reads it, so committing it moves one region;
     *  - `summary` reads `name` in its `helperText()` closure, so its markup moves
     *    with it and comes back as a region;
     *  - `extra` appears only when `kind` is `b`, so changing `kind` changes the
     *    set of fields and falls back to a full render.
     *
     * The heading outside the form is the witness for the documented trade: it
     * shows the same state and must NOT update on a covered commit.
     */
    protected function buildFieldPartialsForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->fieldPartials()
            ->schema([
                TextInput::make('note')->label('Note')->live(),
                TextInput::make('name')->label('Name')->live(),
                TextInput::make('summary')
                    ->label('Summary')
                    ->helperText(fn (): string => 'Summary for '.($this->data['name'] ?? '')),
                TextInput::make('kind')->label('Kind')->live(),
                TextInput::make('extra')->label('Extra')->visibleWhen('kind', 'b'),
            ]);
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
     * A wizard *inside* a create-option modal, with its own navigation handed to
     * the modal footer (`Wizard::navigation(false)`).
     *
     * Two things here are only observable in a browser: the footer mirrors the
     * wizard's step across two sibling Alpine scopes over a window event, and
     * "Next" gates on server-side per-step validation of the option form — which
     * only works because the mounted option form is enumerated as a host form.
     */
    protected function buildOptionWizardForm(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->label('Category')
                    ->options(fn () => $this->categoryOptions)
                    ->getOptionLabelUsing(fn ($value) => $this->categoryOptions[$value] ?? null)
                    ->createOptionForm([
                        Wizard::make('optwiz')->navigation(false)->schema([
                            Step::make('Name')->description('What to call it')->schema([
                                TextInput::make('label')->label('Label')->rules(['required']),
                            ]),
                            Step::make('Detail')->description('Anything else')->schema([
                                TextInput::make('note')->label('Note'),
                            ]),
                        ]),
                    ])
                    ->createOptionModalWidth('2xl')
                    ->createOptionUsing(function (array $data) {
                        $key = 'c'.(count($this->categoryOptions) + 1);
                        $this->categoryOptions[$key] = (string) $data['label'];

                        return $key;
                    }),
            ]);
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
                                    ->placeholder('John Doe')
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

    /**
     * The repeater's table layout: one column per schema field, headed once.
     * Add/remove/reorder run through the same endpoints as the card layout.
     */
    protected function buildRepeaterTableForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Invoice lines')
                    ->description('Short uniform rows, laid out as a table instead of a card per item.')
                    ->schema([
                        Repeater::make('lines')
                            ->table()
                            ->reorderable()
                            ->addButtonLabel('Add line')
                            ->schema([
                                TextInput::make('description')->label('Description'),
                                TextInput::make('quantity')->label('Qty'),
                                TextInput::make('amount')->label('Amount'),
                            ]),
                    ]),
            ]);
    }

    /**
     * A builder: every item picks its own block type from the add picker and is
     * edited with that block's schema.
     */
    protected function buildBuilderForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Page content')
                    ->description('Heterogeneous items — each one carries its own block type.')
                    ->schema([
                        Builder::make('content')
                            ->reorderable()
                            ->collapsible()
                            ->addButtonLabel('Add block')
                            ->blocks([
                                Block::make('heading')
                                    ->label('Heading')
                                    ->icon('bars-3-bottom-left')
                                    ->schema([TextInput::make('text')->label('Text')]),
                                Block::make('paragraph')
                                    ->label('Paragraph')
                                    ->icon('bars-3')
                                    ->schema([Textarea::make('body')->label('Body')->rows(3)]),
                                Block::make('callout')
                                    ->label('Callout')
                                    ->icon('information-circle')
                                    ->schema([
                                        TextInput::make('title')->label('Title'),
                                        Textarea::make('body')->label('Body')->rows(2),
                                    ]),
                            ]),
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

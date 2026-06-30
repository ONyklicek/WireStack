## wire-forms

Build forms inside a Livewire component using the `WithForms` trait and a `form()` method:

    use NyonCode\WireForms\Forms\WithForms;
    use NyonCode\WireForms\Forms\Form;

    class EditUser extends Component
    {
        use WithForms;

        public array $data = [];

        public function form(Form $form): Form
        {
            return $form->statePath('data')->schema([
                Section::make('Profile')->schema([
                    TextInput::make('name')->required(),
                    Select::make('role')->options(Role::class),
                    Toggle::make('is_active'),
                ]),
            ]);
        }
    }

### Fields

Inputs: `TextInput`, `Textarea`, `Select`, `Checkbox`, `CheckboxList`, `Radio`, `Toggle`, `DateTimePicker`,
`ColorPicker`, `FileUpload`, `KeyValue`, `MarkdownEditor`, `RichEditor`, `TiptapEditor`, `CodeEditor`,
`OtpInput`, `Rating`, `Slider`, `Tags`, `Repeater`, `BelongsToSelect`, `MorphToSelect`, `Hidden`.
Display: `Alert`, `Html`, `Placeholder`, `ViewField`. Layout: `Section`, `Grid`, `Fieldset`.

### Conventions

- Fields are `::make($name)` where `$name` is the state key under the form's `statePath`.
- Validation lives on the field: `->required()`, `->rules([...])`, `->minLength()` etc.
- Options accept arrays or an enum class: `->options(Status::class)` (shared `HasOptions`).
- Reactivity is opt-in with `->live()`; default fields use deferred `wire:model`.
- `Select`/`Radio`/`CheckboxList` share the same options API.

### Reactive patterns

Every field closure (`visible()`, `hidden()`, `disabled()`, `afterStateUpdated()`) is evaluated with
Filament-style accessors resolved against the live state bag — works identically in a standalone
`WithForms` form and inside a table action modal:

- `$get('sibling')` — read another field's current value.
- `$get()` — read this field's own value live (reflects a `$set` made earlier in the same closure).
- `$set('sibling', $value)` — write another field (StateContainer-safe, even in action modals).
- `$state` — this field's value, snapshotted at call time.

Conditional field driven by a sibling:

    Select::make('type')->options(['business' => 'Business', 'person' => 'Person'])->live(),
    TextInput::make('vat_id')->visible(fn ($get) => $get('type') === 'business'),

`afterStateUpdated()` runs a callback after the field's value changes (auto-enables `live()`):
the callback receives `$state` (new value), `$old`, `$get`, `$set`, `$component`.

    TextInput::make('type')->afterStateUpdated(
        fn ($state, $set) => $set('vat_id', $state === 'business' ? null : ''),
    );

Hidden fields are skipped during validation, so a `required()` rule on a field hidden by
`visible(fn ($get) => …)` never blocks submit.

### Prefill a form from an action

Action modal forms read/write the `modal.action.formData` bag. Seed initial values with
`fillFormUsing()` (the callback receives the record; `null` for header actions):

    EditAction::make()
        ->form([
            TextInput::make('name')->required(),
            Select::make('role')->options(Role::class),
        ])
        ->fillFormUsing(fn ($record) => [
            'name' => $record->name,
            'role' => $record->role->value,
        ]);

Add extra footer buttons that read/write the in-progress form without submitting it via
`modalFooterActions()` — each `ModalFooterAction` callback gets the live `$data` bag and a `$set`
writer (`->submitsForm()` validates first, `->closesModal()` closes after):

    ->modalFooterActions([
        ModalFooterAction::make('generate_slug')
            ->action(fn ($data, $set) => $set('slug', Str::slug($data['name'] ?? ''))),
    ]);

Use `describe-component-api` to see a field's full fluent surface.

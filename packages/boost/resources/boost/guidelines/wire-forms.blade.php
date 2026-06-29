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

Use `describe-component-api` to see a field's full fluent surface.

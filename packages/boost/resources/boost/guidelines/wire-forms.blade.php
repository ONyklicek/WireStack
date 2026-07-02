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
- `->rules()` accepts a Closure (or Closure entries in the array), evaluated with `$get`/`$set` for rules that depend on live sibling state.
- Options accept arrays or an enum class: `->options(Status::class)` (shared `HasOptions`).
- Reactivity is opt-in with `->live()`; default fields use deferred `wire:model`.
- `Select`/`Radio`/`CheckboxList` share the same options API.
- `Radio` has display variants: `->cards()` (selectable cards, `->inline()` for a row,
  `->hideIndicator()` to drop the dot), `->segmented()` (pill over a track), and `->buttons()`
  (separate buttons, selected filled; `->inline()` for a row). `->icons([value => name])` and
  `->colors([value => color])` add per-option icons/colors (every variant) and are auto-derived
  from a `HasIcon`/`HasColor` enum passed to `->options(Enum::class)`. The `segmented`/`buttons`
  variants take a size via the shared `HasSize` API (`->sm()`/`->md()`/`->lg()`), and `->color(...)`
  sets one group accent (shared `HasColor`).

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

Fluent conditioning shortcuts avoid the Closure for the common "field equals value" case
(pass an array to match "is one of"):

- `->requiredIf('type', 'business')`, `->requiredUnless('type', 'person')`, `->requiredWith('company')`
- `->visibleWhen('type', 'business')`, `->hiddenWhen('type', 'person')`, `->disabledWhen('locked', true)`

`visibleWhen`/`hiddenWhen`/`disabledWhen` are shared Foundation helpers (also on columns, filters,
actions); the `required*` helpers live on the field. Both resolve reactively against live sibling state:

    Select::make('type')->options(['business' => 'Business', 'person' => 'Person'])->live(),
    TextInput::make('vat_id')->visibleWhen('type', 'business')->requiredIf('type', 'business'),

`afterStateUpdated()` runs a callback after the field's value changes (auto-enables `live()`):
the callback receives `$state` (new value), `$old`, `$get`, `$set`, `$component`.

    TextInput::make('type')->afterStateUpdated(
        fn ($state, $set) => $set('vat_id', $state === 'business' ? null : ''),
    );

Hidden fields are skipped during validation, so a `required()` rule on a field hidden by
`visible(fn ($get) => …)` never blocks submit.

Live validation is opt-in per field: `->validateLive()` (validates on each change) or
`->validateOnBlur()` (validates when focus leaves). The host validates just that field during the
reactive roundtrip and refreshes only its error bag entry — the rest of the form is not flagged.
Conditional rules (`requiredIf()` etc.) are honoured live. Cross-field Laravel string rules like
`required_if:other,value` still validate on submit; use `requiredIf()` for the reactive equivalent.

All of this reactivity works for fields inside `Repeater` items too — `afterStateUpdated()`,
live validation, field actions and remote search resolve per item, and `$get`/`$set` read/write
that item's own bag (so `$set('slug', …)` on row 2 touches only row 2).

Selects: `Select` supports server-driven options (`getSearchResultsUsing()` remote search,
`getOptionLabelUsing()`, `preload()`) and create/edit-option modals (`createOptionForm()` +
`createOptionUsing()`, `editOptionForm()` + `fillEditOptionUsing()`/`updateOptionUsing()`) —
both work in standalone forms and inside table action modals. `BelongsToSelect::searchable()`
without `preload()` searches the related table on the server automatically (title-attribute
`like`, limit 50); `preload()` ships the full option list and filters client-side.

    TextInput::make('email')->email()->required()->validateLive(),

Layout components (`Grid`, `Section`, `Fieldset`, …) receive the same `$get`/`$set` accessors in
their `visible()`/`hidden()` closures, so you can show or hide a whole section based on sibling state:

    Section::make('Billing')
        ->schema([TextInput::make('vat_id')])
        ->visible(fn ($get) => $get('type') === 'business'),

### Field-level actions

Attach an interactive `Action` to an input via `suffixAction()`, `prefixAction()` or `hintAction()`.
The action's callback runs on the server with the same `$get`/`$set`/`$state` context as
`afterStateUpdated()` — ideal for lookups (ARES, address verification) or deriving one field from
another:

    TextInput::make('title')->suffixAction(
        Action::make('to_upper')
            ->icon('heroicon-o-arrow-up')
            ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
    );

For a standalone, design-system-styled button bound to a closure (instead of raw `Html::make()`),
use the `Button` field. Presentation (`label`, `icon`, `color`, `size`, `outlined`) mirrors actions:

    Button::make('generate_slug')
        ->label('Generate slug')
        ->icon('heroicon-o-sparkles')
        ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title')))),

Both work in standalone `WithForms` hosts and table action modals; the host's `callFieldAction()`
endpoint re-resolves the field and runs the closure.

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

# Wire Forms

Standalone form system for Laravel Livewire. Works independently or with Wire Table.

## Installation

See the [Wire Forms README](../../packages/forms/README.md) for full installation instructions including Tailwind CSS and Vite setup. Quick version:

```bash
composer require nyoncode/wire-forms
```

Add to your Tailwind content paths:
```js
'./vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
'./vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
```

## Architecture

Forms use a Config + Runtime separation internally (see [ADR 0011](../decisions/0011-form-config-runtime-separation.md)):

```
Form (public API)
├── ConfigBuilder → FormConfig (immutable)
└── FormRuntime
    ├── StateManager (fill, getState, wire:model)
    ├── SaveHandler (validate → mutate → persist → notify)
    └── FormValidationResolver (rules from fields + form-level)
```

Users only interact with the `Form` class. Internal classes are never exposed.

## Single Form

```php
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;
use NyonCode\WireForms\Components\TextInput;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->successMessage('User created');
    }
}
```

```blade
<form wire:submit="$this->form->save">
    {{ $this->form }}
    <button type="submit">Create</button>
</form>
```

## Multi-Form

Methods ending with `Form` are auto-detected (see [ADR 0009](../decisions/0009-single-multi-form-coexistence.md)):

```php
class UserSettings extends Component
{
    use WithForms;

    public function profileForm(Form $form): Form { ... }
    public function passwordForm(Form $form): Form { ... }
}
```

```blade
{{ $this->profileForm }}
{{ $this->passwordForm }}
```

Or use explicit registration:
```php
protected function getForms(): array
{
    return ['profile', 'settings'];
}
```

## Save Lifecycle

The `save()` method follows this exact order:

1. **Validate** — collect rules from fields + form-level rules
2. **Mutate** — `mutateDataBeforeSave()` transforms data
3. **beforeSave** — void hook for observers
4. **Persist** — Eloquent create/update (or custom `using()`)
5. **afterSave** — void hook for side effects
6. **Notify** — success notification via Notifications module (see [ADR 0010](../decisions/0010-form-save-notifications-integration.md))

## Model Modes

```php
$form->model(User::class);   // create mode → User::create($data)
$form->model($user);          // edit mode → $user->update($data)
$form->model(null);            // no model → save() throws exception
```

## Standalone Usage

Works without Livewire (see [ADR 0012](../decisions/0012-form-make-standalone-usage.md)):

```php
$form = Form::make()
    ->schema([TextInput::make('name')->required()])
    ->state(['name' => 'John']);

$data = $form->validate();
```

## Form API Reference

See the [README](../../packages/forms/README.md) for the complete Form API.

## Field Types

- [TextInput](fields/text-input.md)
- [Textarea](fields/textarea.md)
- [Select](fields/select.md)
- [Checkbox](fields/checkbox.md)
- [CheckboxList](fields/checkbox-list.md)
- [Radio](fields/radio.md)
- [Toggle](fields/toggle.md)
- [DateTimePicker](fields/date-time-picker.md)
- [ColorPicker](fields/color-picker.md)
- [FileUpload](fields/file-upload.md)
- [RichEditor](fields/rich-editor.md)
- [Hidden](fields/hidden.md)

## Layout Components

- [Section](fields/section.md)
- [Grid](fields/grid.md)
- [Fieldset](fields/fieldset.md)

## Display Components

- [Placeholder](fields/placeholder.md)
- [Alert](fields/alert.md)
- [Html](fields/html.md)
- [ViewField](fields/view-field.md)

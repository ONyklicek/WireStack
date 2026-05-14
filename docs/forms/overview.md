# Wire Forms

Standalone form system for Laravel Livewire. Works independently or with Wire Table.

## Installation

```bash
composer require nyoncode/wire-forms
```

Add to Tailwind content paths:
```js
'./vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
'./vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
```

---

## Architecture

Forms use Config + Runtime separation internally ([ADR 0011](../decisions/0011-form-config-runtime-separation.md)):

```
Form (public API, Htmlable)
├── ConfigBuilder      → accumulates fluent calls
├── FormConfig         → immutable snapshot
├── FormRuntime        → validate, save, getState
├── StateManager       → wire:model binding, fill, dirty tracking
│   └── delegates to Core StateContainer
├── SaveHandler        → validate → mutate → persist → notify
├── FormValidationResolver → collect rules from fields
│   └── delegates to Core ValidationPipeline
└── FormRenderer       → Blade output
```

Users interact **only** with `Form`. Internal classes are never exposed.

---

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

    public function save(): void
    {
        $this->form()->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Create</button>
</form>
```

---

## Multi-Form

Methods ending with `Form` are auto-detected ([ADR 0009](../decisions/0009-single-multi-form-coexistence.md)):

```php
class UserSettings extends Component
{
    use WithForms;

    public array $profileData = [];
    public array $passwordData = [];

    public function profileForm(Form $form): Form
    {
        return $form
            ->statePath('profileData')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('bio'),
            ]);
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->schema([
                TextInput::make('current_password')->password()->required(),
                TextInput::make('password')->password()->required()->confirmed(),
                TextInput::make('password_confirmation')->password()->required(),
            ]);
    }

    public function saveProfile(): void
    {
        $this->profileForm()->save();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm()->validate();
        $this->user->update(['password' => Hash::make($data['password'])]);
    }
}
```

```blade
<form wire:submit="saveProfile">
    {{ $this->profileForm }}
    <button type="submit">Save Profile</button>
</form>

<form wire:submit="savePassword">
    {{ $this->passwordForm }}
    <button type="submit">Change Password</button>
</form>
```

### Explicit Form Registration

Alternative to auto-detection:

```php
protected function getForms(): array
{
    return ['profile', 'settings'];
}
```

---

## Model Modes

```php
// Create mode — Form::save() calls User::create($data)
$form->model(User::class);

// Edit mode — Form::save() calls $user->update($data)
$form->model($user);

// No model — save() throws, but validate() works
$form->model(null);
```

Introspection:
```php
$form->isCreating();   // true when model is a class string
$form->isEditing();    // true when model is an instance
$form->getModel();     // Model instance or null
```

---

## Standalone Usage (without Livewire)

Works for server-side validation and data processing ([ADR 0012](../decisions/0012-form-make-standalone-usage.md)):

```php
$form = Form::make()
    ->schema([
        TextInput::make('name')->required()->maxLength(255),
        TextInput::make('email')->email()->required(),
    ])
    ->state(['name' => 'John', 'email' => 'john@example.com']);

// Validate only
$validated = $form->validate(); // throws ValidationException on failure

// Validate + save
$form->model(User::class)->save();
```

---

## Form API Reference

### Schema & State

```php
->schema(array $components)          // field definitions
->statePath(string $path)            // Livewire property name for state
->fill(array $data)                  // populate state
->state(array $data)                 // alias for fill()
->getState(): array                  // current state
->getValidationRules(): array        // collected rules
->validate(): array                  // validate and return data
```

### Model & Save

```php
->model(string|Model|null $model)    // Eloquent model (class for create, instance for edit)
->save(): mixed                      // full save lifecycle
->using(Closure $fn)                 // custom save callback (replaces default persist)
```

### Save Lifecycle Hooks

```php
->mutateDataBeforeSave(Closure $fn)  // transform data before persist
->beforeSave(Closure $fn)            // void hook before persist
->afterSave(Closure $fn)             // void hook after persist
```

### Notifications

```php
->successMessage(string $msg)        // custom success notification text
->disableSuccessNotification()       // no notification after save
```

### Validation

```php
->validationMessages(array $msgs)    // custom validation messages
```

### Introspection

```php
->isCreating(): bool                 // model is class string
->isEditing(): bool                  // model is instance
->getModel(): ?Model                 // current model instance
->getFlatComponents(): array         // all components (flat)
```

### Rendering

```php
->toHtml(): string                   // Blade output
(string) $form                       // __toString()
```

### Factory

```php
Form::make()                         // static factory via container
```

### Livewire Binding

```php
->livewire(Component $component)     // bind to Livewire component
```

---

## WithForms Trait

The `WithForms` trait provides:

1. **Auto-detection** — scans for methods ending in `Form` and registers them
2. **Lazy resolution** — forms are only built when first accessed
3. **Caching** — form instances are cached for the request lifecycle
4. **Magic property access** — `$this->profileForm` resolves the form

```php
class MyComponent extends Component
{
    use WithForms;

    // Access via:
    // $this->form            → calls form() method
    // $this->profileForm     → calls profileForm() method
    // $this->settingsForm    → calls settingsForm() method
}
```

---

## Field Types

### Input Fields

- [TextInput](fields/text-input.md) — text, email, password, numeric, tel, url
- [Textarea](fields/textarea.md) — multi-line text
- [Select](fields/select.md) — dropdown, searchable, multiple, relationship
- [Checkbox](fields/checkbox.md) — single checkbox
- [CheckboxList](fields/checkbox-list.md) — multi-checkbox group
- [Radio](fields/radio.md) — radio button group
- [Toggle](fields/toggle.md) — on/off switch
- [DateTimePicker](fields/date-time-picker.md) — unified date/time/datetime ([ADR 0008](../decisions/0008-datetimepicker-unification.md))
- [ColorPicker](fields/color-picker.md) — color selector
- [FileUpload](fields/file-upload.md) — file/image upload
- [RichEditor](fields/rich-editor.md) — WYSIWYG editor
- [Hidden](fields/hidden.md) — hidden field

### Layout Components

- [Grid](fields/grid.md) — CSS grid layout
- [Section](fields/section.md) — collapsible section with heading
- [Fieldset](fields/fieldset.md) — HTML fieldset

### Display Components

- [Placeholder](fields/placeholder.md) — static text
- [Alert](fields/alert.md) — alert message
- [Html](fields/html.md) — raw HTML
- [ViewField](fields/view-field.md) — custom Blade view

### Shared Field API

Every field inherits:

```php
->label(string|Closure $label)
->helperText(string|Closure $text)
->hint(string|Closure $hint)
->hintIcon(string $icon)
->required(bool|Closure $required = true)
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->disabled(bool|Closure $disabled = true)
->size('sm'|'md'|'lg'|'xl')
->columnSpan(int|string $span)          // grid column span
->columnStart(int $start)               // grid column start
->default(mixed $value)                 // default value
->extraAttributes(array $attrs)         // HTML attributes
->live()                                // wire:model.live
->debounce(int $ms = 500)              // wire:model.blur with debounce
->rules(string|array $rules)            // Laravel validation rules
->validationMessages(array $messages)   // custom validation messages
```

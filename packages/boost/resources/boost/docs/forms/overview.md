---
order: 10
---

# Wire Forms

Standalone form system for Laravel Livewire. Works independently or with Wire Table.

> Need to **display** a record read-only instead of editing it? See [Infolists](../core/infolists.md) — the same schema and layout, with display entries instead of input fields.

## Installation

```bash
composer require nyoncode/wire-forms
```

Add to Tailwind content paths:
```js
export default {
    content: [
        // ...current app paths
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
    ]
}
```

---

## How Forms Work

Define a `Form` schema on your Livewire component, bind it to a state path, and render it with `{{ $this->form }}`.

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
        return $form // [tl! focus:start]
            ->statePath('data')
            ->model(User::class)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ])
            ->successMessage('User created'); // [tl! focus:end]
    }

    public function save(): void
    {
        $this->form->save();
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

Methods ending with `Form` are auto-detected:

```php
class UserSettings extends Component
{
    use WithForms;

    public array $profileData = [];
    public array $passwordData = [];

    public function profileForm(Form $form): Form
    {
        return $form // [tl! focus:start]
            ->statePath('profileData')
            ->model($this->user)
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('bio'),
            ]); // [tl! focus:end]
    }

    public function passwordForm(Form $form): Form
    {
        return $form // [tl! focus:start]
            ->statePath('passwordData')
            ->schema([
                TextInput::make('current_password')->password()->required(),
                TextInput::make('password')->password()->required()->rules(['confirmed']),
                TextInput::make('password_confirmation')->password()->required(),
            ]); // [tl! focus:end]
    }

    public function saveProfile(): void
    {
        $this->profileForm->save();
    }

    public function savePassword(): void
    {
        $data = $this->passwordForm->validate();
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

Alternative to auto-detection — return method names exactly as defined:

```php
protected function getForms(): array
{
    return ['profileForm', 'passwordForm'];
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

Works for server-side validation and data processing:

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
->optimisticLock(?string $column = 'updated_at') // abort update if the record changed since fill
```

See [Save Lifecycle → Optimistic Locking](save-lifecycle.md#optimistic-locking) for the concurrent-edit guard.

### Save Lifecycle Hooks

```php
->mutateDataBeforeSave(Closure $fn)  // fn(array $data): array — transform data before persist
->beforeSave(Closure $fn)            // fn(array $data): void — runs before persist
->afterSave(Closure $fn)             // fn(Model|mixed $record): void — runs after persist
```

### Notifications

```php
->successMessage(string|Closure|null $msg)  // custom success notification text; Closure receives $record
->disableSuccessNotification()              // no notification after save
```

### Validation

```php
->validationMessages(array $msgs)    // custom validation messages
```

### State

```php
->disabled(bool $disabled = true)    // make all fields read-only
```

### Authorization

```php
->authorize(bool $usePolicy = true)              // enable model policy auto-resolution (create/update)
->authorizeUsing(?Closure $callback)             // fn(User $user, $record = null): bool — custom auth check
->canSave(): bool                                // whether the current user may save
->isReadOnly(): bool                             // true when authorization denies save
```

When `->authorize()` is enabled the form becomes read-only (and hides the save button) if the current user lacks the `create` or `update` policy permission on the model.

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
- [DateTimePicker](fields/date-time-picker.md) — unified date/time/datetime
- [ColorPicker](fields/color-picker.md) — color selector
- [FileUpload](fields/file-upload.md) — file/image upload
- [RichEditor](fields/rich-editor.md) — WYSIWYG editor
- [Hidden](fields/hidden.md) — hidden field

### Layout Components

Layout and schema components (Grid, Flex, Section, Fieldset, Tabs, Wizard,
Callout, Empty State) live in the shared [Schema](../core/schema/overview.md)
section — the same vocabulary is reused by forms, infolists, and modals.

- [Grid](../core/schema/layout/grid.md) — CSS grid layout
- [Section](../core/schema/layout/section.md) — collapsible section with heading
- [Fieldset](../core/schema/layout/fieldset.md) — HTML fieldset

### Display Components

- [Placeholder](fields/placeholder.md) — static text
- [Alert](fields/alert.md) — alert message
- [Html](fields/html.md) — raw HTML
- [ViewField](fields/view-field.md) — custom Blade view

### Build Your Own

- [Extending Forms](custom-fields.md) — custom fields, display components, presets, and packaging

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
->default(mixed $value)                 // default value (create mode / absent keys)
->defaultOnNull(bool $condition = true) // also fill the default over an edit-mode null
->extraAttributes(array $attrs)         // HTML attributes
->live()                                // wire:model.live
->debounce(int $ms = 500)              // wire:model.blur with debounce
->afterStateUpdated(Closure $callback)  // react to value changes (auto-enables live)
->rules(string|array $rules)            // Laravel validation rules
->validationMessages(array $messages)   // custom validation messages
```

`visible()`, `hidden()`, `disabled()` and `afterStateUpdated()` closures receive live state
accessors (`$get`, `$set`, `$state`). See [Reactive Fields](reactive-fields.md).

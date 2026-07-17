---
order: 20
---

# Form Validation

Wire Forms provides validation at three levels: field-level rules, form-level rules, and programmatic validation via the Core ValidationPipeline.

---

## Field-Level Rules

Each field declares its own validation rules:

```php
TextInput::make('name')
    ->required()
    ->maxLength(255)
    ->rules(['string', 'regex:/^[a-zA-Z\s]+$/']);

TextInput::make('email')
    ->email()
    ->required()
    ->rules('unique:users,email');

TextInput::make('age')
    ->numeric()
    ->rules(['integer', 'min:18', 'max:120']);

Select::make('role')
    ->required()
    ->rules('in:admin,editor,viewer');
```

### Built-in Rule Helpers

Some fields provide fluent helpers that map to Laravel rules:

| Method | Equivalent Rule |
|--------|-----------------|
| `->required()` | `required` |
| `->email()` | `email` |
| `->numeric()` | `numeric` |
| `->integer()` | `integer` |
| `->maxLength(255)` | `max:255` |
| `->minLength(3)` | `min:3` |
| `->url()` | `url` |
| `->tel()` | sets `tel` HTML input type (no validation rule) |

### Custom Validation Messages

```php
TextInput::make('name')
    ->required()
    ->validationMessages([
        'required' => 'Please enter a name.',
        'max' => 'Name is too long.',
    ]);
```

---

## Form-Level Rules

Add rules at the form level that span multiple fields:

```php
Form::make()
    ->schema([
        TextInput::make('password')->password()->required(),
        TextInput::make('password_confirmation')->password()->required(),
    ])
    ->validationMessages([
        'password.confirmed' => 'Passwords do not match.',
    ]);
```

---

## Programmatic Validation

### validate()

Validates state against all collected rules and returns validated data:

```php
// In a Livewire component
public function save(): void
{
    $data = $this->form->validate();
    // $data contains only validated fields
    // Throws Illuminate\Validation\ValidationException on failure
}
```

### getValidationRules()

Inspect the collected rules without validating:

```php
$rules = $this->form->getValidationRules();
// ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], ...]
```

---

## Validation in Save Lifecycle

When calling `$form->save()`, validation happens automatically as the first step:

```
save()
├── 1. Validate ← all field + form rules
├── 2. mutateDataBeforeSave()
├── 3. Plugin hook: form.saving
├── 4. beforeSave()
├── 5. Persist (create/update)
├── 6. Save relationships
├── 7. afterSave()
├── 8. Plugin hook: form.saved
└── 9. Success notification
```

If validation fails, `save()` throws `ValidationException` and steps 2-9 are skipped.

---

## Standalone Validation (without Livewire)

```php
$form = Form::make()
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
    ->state(['name' => '', 'email' => 'not-an-email']);

try {
    $data = $form->validate();
} catch (ValidationException $e) {
    $errors = $e->errors();
    // ['name' => ['The name field is required.'], 'email' => ['The email field must be a valid email address.']]
}
```

## Conditional Rules

Rules can use Closures for dynamic validation. A Closure receives the field's
reactive `$get` / `$set` accessors, so rules can depend on live sibling state.
The Closure can wrap the whole rule set, or sit inside a rules array as a single
entry:

```php
TextInput::make('company_name')
    ->required(fn (callable $get) => $get('type') === 'business')
    ->rules(fn () => $this->isEditing()
        ? 'unique:companies,name,' . $this->getModel()->id
        : 'unique:companies,name');

// Closures may also be individual entries in a rules array:
TextInput::make('slug')->rules([
    'string',
    fn (callable $get) => $get('type') === 'business' ? 'required' : 'nullable',
]);
```

### Conditioning Helpers

Fluent shortcuts express the most common cross-field conditions without writing
a Closure. Each compares another field's live value; passing an array matches
"is one of".

| Method | Behavior |
|--------|----------|
| `->requiredIf('type', 'business')` | required when `type` equals the value (or is one of an array) |
| `->requiredUnless('type', 'individual')` | required unless `type` equals the value |
| `->requiredWith('company')` | required when `company` has a non-empty value |
| `->visibleWhen('type', 'business')` | shown only when `type` matches |
| `->hiddenWhen('type', 'individual')` | hidden when `type` matches |
| `->disabledWhen('locked', true)` | disabled when `locked` matches |

```php
Select::make('department')
    ->visibleWhen('type', 'business')
    ->requiredIf('type', 'business');
```

`visibleWhen` / `hiddenWhen` / `disabledWhen` are shared foundation helpers, so
they are also available on columns, filters, and actions. On surfaces without a
live state context they no-op (keep the component visible/enabled).

Hidden fields are skipped during validation, so a `required` rule on a field the
user cannot currently see never blocks submit.

---

## Live Validation

By default a form validates as a whole on submit. Opt a field into per-field
validation during the reactive roundtrip so its error appears (and clears) as
the user interacts, without flagging the rest of the form:

```php
TextInput::make('email')->email()->required()->validateLive();   // on each change
TextInput::make('name')->required()->validateOnBlur();           // when focus leaves
```

`validateLive()` enables `live()` and `validateOnBlur()` enables `blur` binding,
so the server sees the change and refreshes only that field's error bag entry.
Conditioning helpers such as `requiredIf()` are honoured live, because they read
the current sibling state on each roundtrip. Live validation also works for
fields inside `Repeater` items — each row's field validates against its own
item path (e.g. `data.contacts.0.email`).

> Live validation checks one field at a time. Rules that compare raw sibling
> values via Laravel's string syntax (e.g. `required_if:other,value`) are best
> validated on submit; use `requiredIf()` for the reactive equivalent.

---

## Error Display

Validation errors are automatically bound to Livewire's error bag and displayed next to their respective fields. The state path prefix is applied automatically:

```php
// If statePath('data') and field is TextInput::make('name')
// Error key: data.name
// Livewire displays: @error('data.name')
```

No manual error rendering is needed in Blade.

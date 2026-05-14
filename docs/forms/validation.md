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
| `->confirmed()` | `confirmed` |
| `->url()` | `url` |
| `->tel()` | custom telephone pattern |

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
    $data = $this->form()->validate();
    // $data contains only validated fields
    // Throws Illuminate\Validation\ValidationException on failure
}
```

### getValidationRules()

Inspect the collected rules without validating:

```php
$rules = $this->form()->getValidationRules();
// ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email'], ...]
```

---

## Validation in Save Lifecycle

When calling `$form->save()`, validation happens automatically as the first step:

```
save()
├── 1. Validate ← all field + form rules
├── 2. mutateDataBeforeSave()
├── 3. beforeSave()
├── 4. Persist (create/update)
├── 5. afterSave()
└── 6. Success notification
```

If validation fails, `save()` throws `ValidationException` and steps 2-6 are skipped.

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

---

## FormValidationResolver

Internally, `FormValidationResolver` collects rules from all field components and delegates to the Core `ValidationPipeline`:

```
FormValidationResolver
├── Iterate form schema (flat)
├── Collect rules from each Field (->getRules())
├── Merge form-level rules
├── Merge custom messages
└── Delegate to Core ValidationPipeline
    └── Returns ValidationResult
```

### Core ValidationPipeline

The shared `ValidationPipeline` from wire-core is used by both forms and inline table editing:

```php
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Core\Validation\ValidationResult;

$pipeline = new ValidationPipeline();

$result = $pipeline->validate(
    data: ['name' => '', 'email' => 'bad'],
    rules: ['name' => 'required', 'email' => 'email'],
    messages: ['name.required' => 'Name is mandatory'],
    attributes: ['name' => 'Full Name'],
);

$result->passed();          // false
$result->failed();          // true
$result->errors();          // ['name' => ['Name is mandatory'], 'email' => [...]]
$result->hasError('name');  // true
$result->getError('name');  // ['Name is mandatory']
$result->validatedData();   // [] (empty on failure)
```

### Validatable Contract

Components can implement `Validatable` to participate in pipeline validation:

```php
use NyonCode\WireCore\Core\Validation\Contracts\Validatable;

interface Validatable
{
    public function getValidationRules(): array;
    public function getValidationMessages(): array;
    public function getValidationAttributes(): array;
}
```

---

## Conditional Rules

Rules can use Closures for dynamic validation:

```php
TextInput::make('company_name')
    ->required(fn () => $this->form()->getState()['type'] === 'business')
    ->rules(fn () => $this->isEditing() ? 'unique:companies,name,' . $this->getModel()->id : 'unique:companies,name');

Select::make('department')
    ->visible(fn () => $this->form()->getState()['type'] === 'business')
    ->required(fn () => $this->form()->getState()['type'] === 'business');
```

---

## Error Display

Validation errors are automatically bound to Livewire's error bag and displayed next to their respective fields. The state path prefix is applied automatically:

```php
// If statePath('data') and field is TextInput::make('name')
// Error key: data.name
// Livewire displays: @error('data.name')
```

No manual error rendering is needed in Blade.

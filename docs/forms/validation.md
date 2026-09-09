---
order: 20
summary: Rules at three levels — the field, the form and the pipeline — and which of them wins when they disagree.
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

TextInput::make('email')      // [tl! focus:start]
    ->email()
    ->required()
    ->unique();                // [tl! focus:end]

TextInput::make('age')
    ->numeric()
    ->rules(['integer', 'min:18', 'max:120']);

Select::make('role')
    ->required()
    ->rules('in:admin,editor,viewer');
```

### Type Helpers Are Not Validation

`->email()`, `->numeric()`, `->integer()`, `->url()` and `->tel()` are *type*
presets: they set the HTML input type and inputmode, which picks the phone
keyboard and lets the browser offer its own hint. `->maxLength()` and
`->minLength()` render the `maxlength` / `minlength` attributes. None of them
adds a Laravel rule, and none of them survives a request that did not come from
your form — the server has to be told separately what it will accept:

| Method | What it actually does | The rule to add |
|--------|-----------------------|-----------------|
| `->email()` | `type=email`, `inputmode=email` | `->rules(['email'])` |
| `->numeric()` | `type=number`, `inputmode=decimal` | `->rules(['numeric'])` |
| `->integer()` | `type=number`, `inputmode=numeric`, `step=1` | `->rules(['integer'])` |
| `->url()` | `type=url` | `->rules(['url'])` |
| `->tel()` | `type=tel` | `->rules(['regex:…'])`, or use [PhoneInput](fields/phone-input.md) |
| `->maxLength(255)` | `maxlength="255"` | `->rules(['max:255'])` |
| `->minLength(3)` | `minlength="3"` | `->rules(['min:3'])` |

The helpers that *are* rules are `->required()` (and `->requiredWith()`), which
prepends `required`, and [`->unique()`](#unique-values). Some fields also carry
**implicit** rules they add on their own, because their state cannot be checked
any other way: [`MoneyInput`](fields/money-input.md) validates the amount behind
its formatted text, [`PhoneInput`](fields/phone-input.md) the number behind its
country prefix, [`FileUpload`](fields/file-upload.md) the mime types and sizes it
was configured with, and a field with `options()` an `in:` constraint over its
own option keys (unless you declared one yourself).

> **A cleared numeric field does not need a rule to be safe.** A `<input
> type=number>` submits `''` when it is emptied, and `TextInput` turns that into
> `null` on the way to the record rather than writing an empty string to a
> numeric column. See [Empty Values](fields/text-input.md#empty-values).

### Unique Values

`unique()` builds Laravel's `Rule::unique()` from what the form already knows:
the table from the bound model, the column from the field's name, and — in edit
mode — the record being edited is excluded from its own check.

```php
TextInput::make('email')->unique();                      // unique:users,email, ignoring this user
TextInput::make('email')->unique(ignoreRecord: false);   // every row counts, including this one
TextInput::make('tax_id')->unique(column: 'vat_number'); // a field whose name is not the column
TextInput::make('name')->unique(table: 'companies');     // a form with no ->model()
```

The rule is built when validation runs, not when the schema is declared. That
matters because the record arrives from the form runtime after your `form()`
method has returned — a rule resolved at declaration time would ignore nothing
on the very form it was written for.

Scope the check further with `modifyRuleUsing`, which receives the `Unique` rule:

```php
TextInput::make('email')
    ->unique(modifyRuleUsing: fn (Unique $rule) => $rule->where('team_id', $this->teamId));
```

A form with no `->model()` has no table to infer, so pass one: without either,
`unique()` throws a `FormConfigurationException` naming the field.

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
        TextInput::make('password')->password()->required()->rules(['confirmed']),
        TextInput::make('password_confirmation')->password()->required()->dehydrated(false),
    ])
    ->validationMessages([
        'password.confirmed' => 'Passwords do not match.',
    ]);
```

The confirmation field has a rule and no column behind it, so it is marked
`dehydrated(false)`: it takes part in validation and is dropped before the record
is written. Without that the save would try to set a `password_confirmation`
attribute on the model and fail on the missing column — see
[Save Lifecycle](save-lifecycle.md#what-reaches-the-record).

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
    ->rules(fn (callable $get) => $get('type') === 'business' ? ['min:2'] : []);

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

`validateLive()` enables `live()` and `validateOnBlur()` enables `live.blur` binding,
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

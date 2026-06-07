---
order: 10
---

# Form Fields

Reference for the built-in Wire Forms field and layout components.

## Choose by Use Case

| Use case | Component |
|----------|-----------|
| Single-line text | [TextInput](text-input.md) |
| Multi-line text | [Textarea](textarea.md) |
| Select one option | [Select](select.md) |
| Toggle a boolean value | [Toggle](toggle.md) or [Checkbox](checkbox.md) |
| Select multiple options | [CheckboxList](checkbox-list.md) |
| Pick one visible option | [Radio](radio.md) |
| Free-form tags / chips | [Tags](tags.md) |
| Numeric range slider | [Slider](slider.md) |
| Key-value pair editor | [KeyValue](key-value.md) |
| Star rating | [Rating](rating.md) |
| Choose date or date/time | [DateTimePicker](date-time-picker.md) |
| Pick a color | [ColorPicker](color-picker.md) |
| Upload files | [FileUpload](file-upload.md) |
| Rich text editing | [RichEditor](rich-editor.md) or [TiptapEditor](tiptap-editor.md) |
| Markdown editing | [MarkdownEditor](markdown-editor.md) |
| Code / script input | [CodeEditor](code-editor.md) |
| OTP / PIN code | [OtpInput](otp-input.md) |
| Hidden form metadata | [Hidden](hidden.md) |
| Select a related record | [BelongsToSelect](belongs-to-select.md) |
| Select a polymorphic target | [MorphToSelect](morph-to-select.md) |
| Manage repeated groups or child rows | [Repeater](repeater.md) |

## Layout Components

| Component | Purpose |
|-----------|---------|
| [Grid](grid.md) | Responsive multi-column layout |
| [Section](section.md) | Group fields under a heading |
| [Fieldset](fieldset.md) | Group related fields with a border |

## Display Components

| Component | Purpose |
|-----------|---------|
| [Placeholder](placeholder.md) | Read-only value display |
| [Alert](alert.md) | Contextual message inside the form |
| [Html](html.md) | Render trusted HTML content |
| [ViewField](view-field.md) | Render a custom Blade partial as a field |

## Build Your Own

Need a field that is not listed here? See [Extending Forms](../custom-fields.md)
for building custom fields and display components, reusable presets, and
packaging fields into a plugin.

## Common Field API

Every field inherits the following methods from the shared `Field` base class. Individual field docs focus on field-specific options; refer back here for anything not listed there.

### Label and help text

| Method | Example |
|--------|---------|
| `label(string\|Closure)` | `->label('Full name')` |
| `helperText(string\|Closure)` | `->helperText('Used for login')` |
| `hint(string\|Closure)` | `->hint('Optional')` |
| `hintIcon(string)` | `->hintIcon('information-circle')` |
| `hintColor(string)` | `->hintColor('warning')` |
| `tooltip(string\|Closure)` | `->tooltip('Shown on hover')` |

### Visibility and state

| Method | Example |
|--------|---------|
| `visible(bool\|Closure)` | `->visible(fn () => $this->showField)` |
| `hidden(bool\|Closure)` | `->hidden()` |
| `disabled(bool\|Closure)` | `->disabled()` |
| `readOnly(bool\|Closure)` | `->readOnly()` |

### Default value

```php
TextInput::make('status')->default('draft')
TextInput::make('user_id')->default(fn () => auth()->id())
```

### Validation

| Method | Example |
|--------|---------|
| `required()` | `->required()` |
| `rules(array\|string)` | `->rules(['min:2', 'max:255'])` |
| `validationMessages(array)` | `->validationMessages(['required' => 'Povinné pole'])` |

### Live updates

| Method | Behaviour |
|--------|-----------|
| `live()` | Triggers Livewire update on every input event (with 250 ms debounce) |
| `lazy()` | Triggers Livewire update on blur |
| `debounce(int $ms)` | Overrides debounce delay for `live()` |

### Prefix and suffix

Available on TextInput, Textarea, and Select.

| Method | Example |
|--------|---------|
| `prefix(string)` | `->prefix('CZK')` |
| `suffix(string)` | `->suffix('%')` |
| `prefixIcon(string)` | `->prefixIcon('magnifying-glass')` |
| `suffixIcon(string)` | `->suffixIcon('check')` |

### Other

| Method | Example |
|--------|---------|
| `placeholder(string\|Closure)` | `->placeholder('Enter value...')` |
| `autofocus()` | `->autofocus()` |
| `extraAttributes(array)` | `->extraAttributes(['data-testid' => 'name'])` |
| `columnSpan(int\|string)` | `->columnSpan(2)` — span columns inside a [Grid](grid.md) |

## Common Patterns

### Basic create or edit form

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Toggle;

->schema([
    TextInput::make('name')
        ->required()
        ->maxLength(255),

    TextInput::make('email')
        ->email()
        ->required(),

    Select::make('role')
        ->options([
            'admin' => 'Admin',
            'editor' => 'Editor',
            'viewer' => 'Viewer',
        ])
        ->required(),

    Toggle::make('active'),
])
```

### Group fields into sections

```php
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;

->schema([
    Section::make('User')
        ->schema([
            Grid::make(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),
        ]),
])
```

## Related Docs

- [Forms Overview](../overview.md)
- [Validation](../validation.md)
- [Save Lifecycle](../save-lifecycle.md)

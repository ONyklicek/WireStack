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
| Choose date or date/time | [DateTimePicker](date-time-picker.md) |
| Pick a color | [ColorPicker](color-picker.md) |
| Upload files | [FileUpload](file-upload.md) |
| Rich text editing | [RichEditor](rich-editor.md) |
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

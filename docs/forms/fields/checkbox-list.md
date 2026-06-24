# CheckboxList

Multiple checkboxes from an options array.

```php
use NyonCode\WireForms\Components\CheckboxList;
```

## Usage

```php
CheckboxList::make('permissions')
    ->options([
        'create' => 'Create',
        'read'   => 'Read',
        'update' => 'Update',
        'delete' => 'Delete',
    ])
    ->columns(2)
    ->searchable()
    ->bulkToggleable()
```

## Dynamic Options

```php
CheckboxList::make('roles')
    ->options(fn () => Role::pluck('name', 'id')->toArray())
```

## Enum Options

Pass a PHP enum class to expand its cases into `value => label` options. Labels come from
`getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`, otherwise the
case name is headlined. See [Select › Enum Options](select.md#enum-options) for details.

```php
CheckboxList::make('permissions')->options(Permission::class)
```

## Multi-Column Layout

```php
CheckboxList::make('features')
    ->options([...])
    ->columns(3)
```

## Search

```php
CheckboxList::make('permissions')
    ->options([...])
    ->searchable()
    ->searchPrompt('Filter permissions...')
```

## Bulk Toggle

```php
CheckboxList::make('permissions')
    ->bulkToggleable()
    ->selectAllLabel('Select All')
    ->deselectAllLabel('Deselect All')
```

## Grouped Options

When using `groups()`, each key is a group heading and its value is an array of `value => label` pairs.

```php
CheckboxList::make('permissions')
    ->groups([
        'Posts' => ['create_post' => 'Create', 'edit_post' => 'Edit', 'delete_post' => 'Delete'],
        'Users' => ['create_user' => 'Create', 'edit_user' => 'Edit'],
    ])
```

Calling `groups()` automatically enables the grouped layout. You can also call `grouped()` explicitly.

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Option list, or an enum class (`value => label`) |
| `columns(int)` | int | Number of columns (default `1`) |
| `searchable(bool)` | bool | Enable filter-by-label search box |
| `searchPrompt(string\|null)` | string | Placeholder for the search input |
| `bulkToggleable(bool)` | bool | Show select-all / deselect-all controls |
| `selectAllLabel(string\|null)` | string | Label for the select-all button |
| `deselectAllLabel(string\|null)` | string | Label for the deselect-all button |
| `grouped(bool)` | bool | Enable grouped layout |
| `groups(array\|Closure)` | array | Group definitions (also enables grouped layout) |
| `default(array\|Closure)` | array | Pre-selected values |
| `disabled(bool\|Closure)` | bool | Disable all checkboxes |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

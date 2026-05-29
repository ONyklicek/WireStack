# Select

Dropdown select with static or dynamic options, search, and multi-select.

```php
use NyonCode\WireForms\Components\Select;
```

## Basic Usage

```php
Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
```

## Dynamic Options

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')
```

## Searchable

```php
Select::make('user_id')
    ->options(fn () => User::pluck('name', 'id')->toArray())
    ->searchable()
    ->noSearchResultsMessage('No users found')
    ->searchPrompt('Type to search...')
    ->loadingMessage('Loading...')
```

## Multi-Select

```php
Select::make('tags')
    ->multiple()
    ->maxItems(5)
    ->minItems(1)
    ->options([...])
```

## Relationship

```php
Select::make('author_id')
    ->relationship('author', 'name')
    ->searchable()
```

## Native vs Custom

```php
Select::make('country')
    ->native()          // browser-native <select>
    ->native(false)     // custom styled dropdown (default)
```

## Boolean Select

```php
Select::make('active')
    ->boolean()         // Yes/No options
```

## Disabled Options

Render specific options as non-selectable:

```php
Select::make('status')
    ->options([
        'draft'     => 'Draft',
        'review'    => 'In Review',
        'published' => 'Published',
        'archived'  => 'Archived',
    ])
    ->disabledOptions(['archived'])
```

Dynamic disabled options:

```php
Select::make('tier')
    ->options(Plan::pluck('name', 'id')->toArray())
    ->disabledOptions(fn () => Plan::unavailable()->pluck('id')->toArray())
```

## HTML in Options

```php
Select::make('color')
    ->allowHtml()
    ->options([
        'red' => '<span class="text-red-500">Red</span>',
    ])
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `options(array\|Closure)` | array | Static or dynamic options (`value => label`) |
| `searchable()` | bool | Enable option search |
| `multiple()` | bool | Allow multiple selections |
| `native()` | bool | Use the browser-native `<select>` element |
| `maxItems(int\|null)` | int | Maximum selected items (multi-select) |
| `minItems(int\|null)` | int | Minimum selected items (multi-select) |
| `disabledOptions(array\|Closure)` | array | Option keys that are rendered as disabled |
| `noSearchResultsMessage(string\|null)` | string | Message when search finds nothing |
| `loadingMessage(string\|null)` | string | Message while options are loading |
| `searchPrompt(string\|null)` | string | Prompt shown in the search box |
| `allowHtml()` | bool | Render option labels as HTML |
| `boolean()` | — | Shorthand for Yes/No options |
| `relationship(string, string)` | — | Load options from a relationship |
| `placeholder(string\|Closure)` | string | Empty/blank option label |
| `disabled(bool\|Closure)` | bool | Disable the select |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

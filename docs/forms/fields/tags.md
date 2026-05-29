# Tags

Free-form tag input. The user types text and commits it as a chip by pressing Enter or comma. Supports predefined suggestions, limits, and relationship mode.

```php
use NyonCode\WireForms\Components\Tags;
```

## Basic Usage

```php
Tags::make('labels')
```

State is an array of strings: `['php', 'laravel', 'vue']`.

## With Suggestions

```php
Tags::make('skills')
    ->suggestions(['PHP', 'Laravel', 'Vue', 'React', 'TypeScript'])
```

When `allowNew(false)` only suggestions can be picked:

```php
Tags::make('category')
    ->suggestions(fn () => Category::pluck('name')->toArray())
    ->allowNew(false)
```

## Limits

```php
Tags::make('tags')
    ->minItems(1)
    ->maxItems(5)
```

## Split Keys

By default Enter and comma commit a tag. Override if needed:

```php
Tags::make('tags')
    ->splitKeys(['Enter', ' '])   // space-separated tags
```

## Relationship

```php
Tags::make('tags')
    ->relationship('tags', 'name')   // many-to-many
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `suggestions(array\|Closure)` | array | Predefined values shown as autocomplete |
| `splitKeys(array)` | array | Keys that commit the input (default `['Enter', ',']`) |
| `minItems(int\|null)` | int | Minimum tag count |
| `maxItems(int\|null)` | int | Maximum tag count |
| `allowNew(bool)` | bool | Allow tags not in suggestions (default `true`) |
| `allowDuplicates(bool)` | bool | Allow the same tag twice (default `false`) |
| `relationship(string, string)` | — | Many-to-many relationship name and title attribute |
| `placeholder(string\|Closure)` | string | Input placeholder |
| `disabled(bool\|Closure)` | bool | Disable the input |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `live()` | — | Trigger Livewire update after each tag change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

# Textarea

Multi-line text input.

```php
use NyonCode\WireForms\Components\Textarea;
```

## Usage

```php
Textarea::make('description')
    ->rows(5)
    ->cols(40)
    ->minLength(10)
    ->maxLength(1000)
    ->autosize()
```

## Autosize

```php
Textarea::make('notes')
    ->autosize()    // grows automatically as user types
    ->rows(3)       // minimum visible rows before content triggers resize
```

## Spellcheck

```php
Textarea::make('content')
    ->spellcheck()           // force enable browser spellcheck
    ->spellcheck(false)      // force disable browser spellcheck
// null (default) — inherits from browser/OS setting
```

## Live Updates

```php
Textarea::make('bio')
    ->live()
    ->debounce(500)
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `rows(int)` | int | Minimum visible row count (default `3`) |
| `cols(int\|null)` | int | Fixed column width |
| `minLength(int\|null)` | int | Minimum character count |
| `maxLength(int\|null)` | int | Maximum character count |
| `autosize()` | bool | Auto-resize height to fit content |
| `spellcheck(bool\|null)` | bool | Force browser spellcheck on/off (`null` = inherit) |
| `placeholder(string\|Closure)` | string | Placeholder text |
| `disabled(bool\|Closure)` | bool | Disable the textarea |
| `readOnly(bool\|Closure)` | bool | Make the textarea read-only |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire on every keystroke |
| `debounce(int)` | ms | Debounce delay for `live()` |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

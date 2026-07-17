# Button

An interactive, design-system-styled button that runs a closure on the server — the supported
alternative to hand-rolling a `<button>` inside an [Html](html.md) field (which renders raw markup
and bypasses the palette).

```php
use NyonCode\WireForms\Components\Button;
```

## Basic Usage

The `action()` closure runs with the form's reactive `$get` / `$set` / `$state` accessors, so a
button can read and write other fields:

```php
Button::make('generate_slug')
    ->label('Generate slug')
    ->icon('heroicon-o-sparkles')
    ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title'))))
```

## Appearance

Presentation is delegated to an internal `Action`, so a button shares the exact styling and colour
palette as table and modal actions:

```php
Button::make('verify')
    ->label('Verify now')
    ->icon('heroicon-o-check', 'after')  // icon position: 'before' (default) or 'after'
    ->color('success')                   // any palette colour
    ->size('lg')                         // 'xs' | 'sm' | 'md' | 'lg'
    ->outlined()
```

| Method | Purpose |
|--------|---------|
| `label(string\|Closure)` | Button text |
| `icon(string, ?position)` | Leading (`'before'`) or trailing (`'after'`) icon |
| `color(string\|Color\|Closure)` | Palette colour |
| `size(string)` | `xs` / `sm` / `md` / `lg` |
| `outlined(bool)` | Outlined instead of solid |
| `action(Closure)` | Server callback, receives `$get` / `$set` / `$state` / `$component` |

## Notes

- The button dispatches through the same `callFieldAction()` endpoint as field
  [affix actions](../reactive-fields.md#field-actions-and-buttons); it works in a standalone
  `WithForms` form and inside a table action modal.
- A Button is not part of form state and adds no validation — it is a trigger, not an input.
- Respects `disabled()` and `visible()` like any other field.

See [Common Field API](index.md#common-field-api) for shared methods.

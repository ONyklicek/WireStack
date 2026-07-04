---
order: 20
---

# Callout

A soft, colored notice box with an optional heading, icon, and dismiss button.
Body content comes from child components (a schema) or a plain string via
`content()`.

```php
use NyonCode\WireCore\Foundation\Schema\Callout;
```

## Usage

```php
Callout::make()
    ->warning()
    ->icon('exclamation-triangle')
    ->heading('Heads up')
    ->content('This action cannot be undone.')
```

With child components instead of a string body:

```php
Callout::make()
    ->info()
    ->heading('Billing')
    ->schema([
        Placeholder::make('plan')->content('Pro'),
    ])
```

## Colors

Colors delegate to the canonical alert palette. Use the semantic shortcuts or set
any registered color directly:

```php
Callout::make()->info();      // ->color('info')
Callout::make()->success();
Callout::make()->warning();
Callout::make()->danger();
Callout::make()->color('primary');
```

## Dismissible

```php
Callout::make()->danger()->dismissible()->content('...')
```

## Methods

| Method | Description |
|--------|-------------|
| `heading(string\|Closure)` | Bold heading above the body |
| `title(string\|Closure)` | Alias for `heading()` |
| `content(string\|Closure)` | Plain-string body (alternative to a child schema) |
| `color(string\|Color)` | Set the color hue (default `info`) |
| `info()` / `success()` / `warning()` / `danger()` | Color shortcuts |
| `icon(string\|Icon)` | Icon rendered next to the heading |
| `dismissible(bool)` | Show a dismiss button |

## Standalone Tag

The same component is available as a Blade tag outside a schema:

```blade
<x-wire::callout color="warning" heading="Heads up">
    This action cannot be undone.
</x-wire::callout>
```

In forms, the [Alert](../../forms/fields/alert.md) display field is the
field-style alias of this component.

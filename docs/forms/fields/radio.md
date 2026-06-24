# Radio

Radio button group for single-choice selection.

```php
use NyonCode\WireForms\Components\Radio;
```

## Usage

```php
Radio::make('priority')
    ->options([
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ])
```

## Dynamic Options

```php
Radio::make('plan')
    ->options(fn () => Plan::active()->pluck('name', 'slug')->toArray())
```

## Enum Options

Pass a PHP enum class to expand its cases into `value => label` options. Labels come from
`getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`, otherwise the
case name is headlined. The field is also auto-constrained to the enum's values with an `in:`
rule. See [Select › Enum Options](select.md#enum-options) for details.

```php
Radio::make('status')->options(Status::class)
```

## Descriptions

```php
Radio::make('plan')
    ->options([
        'free' => 'Free',
        'pro'  => 'Professional',
    ])
    ->descriptions([
        'free' => 'Limited features, no support',
        'pro'  => 'All features, priority support',
    ])
```

Dynamic descriptions:

```php
Radio::make('plan')
    ->options(fn () => Plan::pluck('name', 'slug')->toArray())
    ->descriptions(fn () => Plan::pluck('description', 'slug')->toArray())
```

## Inline Layout

```php
Radio::make('size')
    ->options(['s' => 'S', 'm' => 'M', 'l' => 'L'])
    ->inline()
```

## Boolean

```php
Radio::make('newsletter')
    ->boolean()      // Yes/No options (uses translation keys wire-forms::fields.yes / no)
```

## Live Updates

```php
Radio::make('delivery_method')
    ->options([...])
    ->live()    // re-renders the form on every change
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `options(array\|string\|Closure)` | array | Option list, or an enum class (`value => label`) |
| `descriptions(array\|Closure)` | array | Per-option description text (`value => description`) |
| `inline(bool)` | bool | Display options horizontally |
| `boolean()` | — | Shorthand for Yes/No radio group |
| `default(mixed\|Closure)` | mixed | Pre-selected value |
| `disabled(bool\|Closure)` | bool | Disable all radio buttons |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

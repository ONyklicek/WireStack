---
order: 10
---

# Section

Collapsible section with heading, description, and icon. Groups child components
visually. Shared schema vocabulary — the same layout renders in forms and
infolists.

```php
use NyonCode\WireCore\Foundation\Schema\Section;
```

## Usage

```php
Section::make('personal')
    ->label('Personal Information')
    ->description('Basic details about the user.')
    ->icon('user')
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email(),
    ])
    ->columns(2)
```

## Collapsible

```php
Section::make('advanced')
    ->label('Advanced Settings')
    ->collapsible()
    ->collapsed()      // start collapsed
    ->schema([...])
```

## Compact Mode

```php
Section::make('info')
    ->compact()        // reduced padding
    ->schema([...])
```

## Aside Layout

```php
Section::make('info')
    ->aside()          // label on the left, content on the right
    ->schema([...])
```

## Methods

| Method | Description |
|--------|-------------|
| `description(string)` | Description below the heading |
| `icon(string)` | Icon next to the heading |
| `columns(int)` | Grid columns inside the section |
| `collapsible()` | Allow collapsing |
| `collapsed()` | Start collapsed |
| `compact()` | Reduced padding |
| `aside()` | Side-by-side layout |
| `headerActions(array)` | Actions rendered in the section header (alias for `actions()`) |

> In forms you may also import the thin `NyonCode\WireForms\Components\Layout\Section`
> alias (deprecated in v2.0). It only swaps in form-specific markup; prefer the
> canonical schema `Section` above.

## Related Docs

- [Grid](grid.md)
- [Fieldset](fieldset.md)
- [Tabs](tabs.md)

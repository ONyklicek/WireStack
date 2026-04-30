# Grid

Multi-column grid layout for organizing fields.

```php
use NyonCode\WireForms\Components\Layout\Grid;
```

## Usage

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name')->columnSpan(1),
        TextInput::make('last_name')->columnSpan(1),
        Textarea::make('bio')->columnSpanFull(),
    ])
```

## Methods

| Method | Description |
|--------|-------------|
| `columns(int)` | Number of grid columns (default: 2) |

Child fields use `columnSpan(int)` and `columnSpanFull()` to control their width.

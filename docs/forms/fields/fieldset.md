# Fieldset

HTML fieldset with legend for grouping fields.

```php
use NyonCode\WireForms\Components\Layout\Fieldset;
```

## Usage

```php
Fieldset::make('address')
    ->label('Address')
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
    ->columns(3)
```

## Methods

| Method | Description |
|--------|-------------|
| `columns(int)` | Grid columns inside the fieldset |

# KeyValue

Inline key-value pair editor for dictionary / map-like data (environment variables, metadata, config options).

```php
use NyonCode\WireForms\Components\KeyValue;
```

## Basic Usage

```php
KeyValue::make('metadata')
    ->keyLabel('Key')
    ->valueLabel('Value')
```

## State Format

State is stored as `array<int, array{key: string, value: string}>`:

```php
[
    ['key' => 'color',  'value' => 'blue'],
    ['key' => 'size',   'value' => 'large'],
]
```

To convert to an associative array for persistence, use `mutateDataBeforeSave`:

```php
->mutateDataBeforeSave(function (array $data): array {
    $data['metadata'] = collect($data['metadata'])
        ->pluck('value', 'key')
        ->all();
    return $data;
})
```

## Fixed Keys

Prevent the user from editing key names (value-only mode):

```php
KeyValue::make('config')
    ->keyEditable(false)
    ->default([
        ['key' => 'timeout',  'value' => '30'],
        ['key' => 'retries',  'value' => '3'],
    ])
```

## Placeholders

```php
KeyValue::make('headers')
    ->keyPlaceholder('Header name')
    ->valuePlaceholder('Header value')
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `keyLabel(string\|Closure\|null)` | string | Column header for keys (default `'Key'`) |
| `valueLabel(string\|Closure\|null)` | string | Column header for values (default `'Value'`) |
| `keyPlaceholder(string\|null)` | string | Placeholder for key inputs |
| `valuePlaceholder(string\|null)` | string | Placeholder for value inputs |
| `addable(bool)` | bool | Allow adding new pairs (default `true`) |
| `deletable(bool)` | bool | Allow deleting pairs (default `true`) |
| `reorderable(bool)` | bool | Allow drag reordering (default `false`) |
| `keyEditable(bool)` | bool | Allow editing key names (default `true`) |
| `disabled(bool\|Closure)` | bool | Disable all interactions |
| `required()` | — | Mark as required |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

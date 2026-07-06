# KeyValue

Inline editor párů klíč-hodnota pro slovníková / map-like data (proměnné prostředí, metadata, config volby).

```php
use NyonCode\WireForms\Components\KeyValue;
```

## Základní použití

```php
KeyValue::make('metadata')
    ->keyLabel('Key')
    ->valueLabel('Value')
```

## Formát stavu

Stav je uložen jako `array<int, array{key: string, value: string}>`:

```php
[
    ['key' => 'color',  'value' => 'blue'],
    ['key' => 'size',   'value' => 'large'],
]
```

Pro převod na asociativní pole pro perzistenci použijte `mutateDataBeforeSave`:

```php
->mutateDataBeforeSave(function (array $data): array {
    $data['metadata'] = collect($data['metadata'])
        ->pluck('value', 'key')
        ->all();
    return $data;
})
```

## Pevné klíče

Zabraňte uživateli editovat názvy klíčů (režim jen hodnota):

```php
KeyValue::make('config')
    ->keyEditable(false)
    ->default([
        ['key' => 'timeout',  'value' => '30'],
        ['key' => 'retries',  'value' => '3'],
    ])
```

## Placeholdery

```php
KeyValue::make('headers')
    ->keyPlaceholder('Header name')
    ->valuePlaceholder('Header value')
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `keyLabel(string\|Closure\|null)` | string | Hlavička sloupce pro klíče (výchozí `'Key'`) |
| `valueLabel(string\|Closure\|null)` | string | Hlavička sloupce pro hodnoty (výchozí `'Value'`) |
| `keyPlaceholder(string\|null)` | string | Placeholder pro key inputy |
| `valuePlaceholder(string\|null)` | string | Placeholder pro value inputy |
| `addable(bool)` | bool | Povolit přidávání nových párů (výchozí `true`) |
| `deletable(bool)` | bool | Povolit mazání párů (výchozí `true`) |
| `reorderable(bool)` | bool | Povolit přeřazování tažením (výchozí `false`) |
| `keyEditable(bool)` | bool | Povolit editaci názvů klíčů (výchozí `true`) |
| `disabled(bool\|Closure)` | bool | Znepřístupnit všechny interakce |
| `required()` | — | Označit jako povinné |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).

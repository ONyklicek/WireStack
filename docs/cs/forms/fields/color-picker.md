# ColorPicker

Výběr barvy s volbou formátu a volitelnými vzorky.

```php
use NyonCode\WireForms\Components\ColorPicker;
```

## Použití

```php
ColorPicker::make('brand_color')
    ->hex()        // #RRGGBB (výchozí)

ColorPicker::make('bg')
    ->hsl()        // hsl(h, s%, l%)

ColorPicker::make('overlay')
    ->rgba()       // rgba(r, g, b, a)

ColorPicker::make('text')
    ->rgb()        // rgb(r, g, b)
```

## Explicitní formát

```php
ColorPicker::make('color')
    ->format('hex')    // 'hex', 'hsl', 'rgb', 'rgba'
```

## Vzorky

Poskytněte seznam předdefinovaných barev, které uživatel může kliknutím okamžitě vybrat.

```php
ColorPicker::make('brand_color')
    ->swatches(['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#a855f7'])
```

Dynamické vzorky z closury:

```php
ColorPicker::make('theme_color')
    ->swatches(fn () => $this->record->team->allowed_colors)
```

## Live aktualizace

```php
ColorPicker::make('preview_color')
    ->live()    // aktualizace při každé změně pro real-time náhled
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `hex()` | — | Uložit jako `#RRGGBB` (výchozí) |
| `hsl()` | — | Uložit jako `hsl(h, s%, l%)` |
| `rgb()` | — | Uložit jako `rgb(r, g, b)` |
| `rgba()` | — | Uložit jako `rgba(r, g, b, a)` |
| `format(string)` | string | Explicitní formát: `hex`, `hsl`, `rgb`, `rgba` |
| `swatches(array\|Closure)` | array | Předdefinované hex barvy zobrazené jako klikatelné vzorky |
| `default(string\|Closure)` | string | Předvyplněná hodnota barvy |
| `disabled(bool\|Closure)` | bool | Znepřístupnit výběr a vzorky |
| `live()` | — | Spustit Livewire update při každé změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#common-field-api).

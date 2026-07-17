# Toggle

Přepínač pro boolean hodnoty s přizpůsobitelným vzhledem.

```php
use NyonCode\WireForms\Components\Toggle;
```

## Použití

```php
Toggle::make('is_active')
    ->label('Active')
    ->default(true)
```

## Přizpůsobení

```php
Toggle::make('notifications_enabled')
    ->onLabel('On')
    ->offLabel('Off')
    ->onColor('success')
    ->offColor('danger')
    ->onIcon('check')
    ->offIcon('x')
    ->inline()
```

## Live aktualizace

```php
Toggle::make('dark_mode')
    ->live()    // okamžitě překreslí formulář při změně
```

## Podmíněné znepřístupnění

```php
Toggle::make('published')
    ->disabled(fn () => ! auth()->user()->can('publish'))
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `onLabel(string\|Closure\|null)` | string | Label zobrazený, když je zapnuto |
| `offLabel(string\|Closure\|null)` | string | Label zobrazený, když je vypnuto |
| `onColor(string\|Color)` | string | Barva když zapnuto — libovolná barva palety (viz [Colors](../../core/foundation.md#barvy)) |
| `offColor(string\|Color)` | string | Barva když vypnuto |
| `onIcon(string\|Icon\|null)` | string | Ikona když zapnuto |
| `offIcon(string\|Icon\|null)` | string | Ikona když vypnuto |
| `inline(bool)` | bool | Zobrazit label inline s přepínačem (výchozí `true`) |
| `default(bool\|Closure)` | bool | Předvyplněná hodnota |
| `disabled(bool\|Closure)` | bool | Znepřístupnit přepínač |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

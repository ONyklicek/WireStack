# Textarea

Víceřádkový textový input.

```php
use NyonCode\WireForms\Components\Textarea;
```

## Použití

```php
Textarea::make('description')
    ->rows(5)
    ->cols(40)
    ->minLength(10)
    ->maxLength(1000)
    ->autosize()
```

## Autosize

```php
Textarea::make('notes')
    ->autosize()    // roste automaticky, jak uživatel píše
    ->rows(3)       // minimum viditelných řádků, než obsah spustí změnu velikosti
```

## Kontrola pravopisu

```php
Textarea::make('content')
    ->spellcheck()           // vynutit zapnutí kontroly pravopisu prohlížeče
    ->spellcheck(false)      // vynutit vypnutí kontroly pravopisu prohlížeče
// null (výchozí) — zdědí z nastavení prohlížeče/OS
```

## Live aktualizace

```php
Textarea::make('bio')
    ->live()
    ->debounce(500)
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `rows(int)` | int | Minimální počet viditelných řádků (výchozí `3`) |
| `cols(int\|null)` | int | Pevná šířka sloupce |
| `minLength(int\|null)` | int | Minimální počet znaků |
| `maxLength(int\|null)` | int | Maximální počet znaků |
| `autosize()` | bool | Auto-změna výšky podle obsahu |
| `spellcheck(bool\|null)` | bool | Vynutit kontrolu pravopisu prohlížeče on/off (`null` = zdědit) |
| `placeholder(string\|Closure)` | string | Placeholder text |
| `disabled(bool\|Closure)` | bool | Znepřístupnit textarea |
| `readOnly(bool\|Closure)` | bool | Udělat textarea read-only |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire při každém stisku klávesy |
| `debounce(int)` | ms | Debounce prodleva pro `live()` |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

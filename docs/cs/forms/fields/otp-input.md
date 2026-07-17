# OtpInput

OTP / PIN input — N samostatných znakových boxů s automatickým posunem fokusu, podporou vložení a navigací šipkami.

```php
use NyonCode\WireForms\Components\OtpInput;
```

## Základní použití

```php
OtpInput::make('code')
    ->length(6)
```

Uložená hodnota je prostý řetězec: `'123456'`.

## Jen číslice

```php
OtpInput::make('pin')
    ->length(4)
    ->numericOnly()    // inputmode="numeric", přijímá jen 0-9
```

## Maskované (styl hesla)

```php
OtpInput::make('pin')
    ->length(4)
    ->masked()
```

## Vizuální oddělovač

```php
OtpInput::make('code')
    ->length(6)
    ->separator(3)    // vykreslí jako: [x][x][x] — [x][x][x]
```

## Vzor ověřovacího kódu

```php
OtpInput::make('verification_code')
    ->length(6)
    ->numericOnly()
    ->required()
    ->live()
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `length(int)` | int | Počet jednotlivých input boxů (výchozí `6`) |
| `numericOnly(bool)` | bool | Přijímat jen číslice 0–9 |
| `masked(bool)` | bool | Maskovat znaky jako pole hesla |
| `separator(int)` | int | Zobrazit oddělovač pomlčkou každých N znaků |
| `disabled(bool\|Closure)` | bool | Znepřístupnit všechny boxy |
| `readOnly(bool\|Closure)` | bool | Read-only režim |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při každé změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

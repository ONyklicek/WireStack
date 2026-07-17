# Checkbox

Jeden checkbox pro boolean hodnoty.

```php
use NyonCode\WireForms\Components\Checkbox;
```

## Použití

```php
Checkbox::make('agree_terms')
    ->label('I agree to the terms')
    ->default(false)
    ->description('You must agree before continuing')
    ->inline()
```

## Podmíněná viditelnost

```php
Checkbox::make('receive_newsletter')
    ->label('Subscribe to newsletter')
    ->visible(fn () => $this->email !== null)
```

## Disabled stav

```php
Checkbox::make('is_verified')
    ->label('Email verified')
    ->disabled()
    ->default(fn () => $this->record?->email_verified_at !== null)
```

## Live aktualizace

```php
Checkbox::make('show_advanced')
    ->live()    // překreslí formulář při přepnutí
```

## Metody

| Metoda | Typ | Popis |
|--------|------|-------------|
| `description(string\|Closure\|null)` | string | Nápověda vykreslená pod checkboxem |
| `inline(bool)` | bool | Zobrazit label inline vedle checkboxu |
| `default(mixed\|Closure)` | mixed | Předvyplněná hodnota (`true` nebo `false`) |
| `disabled(bool\|Closure)` | bool | Znepřístupnit checkbox |
| `readOnly(bool\|Closure)` | bool | Udělat checkbox read-only |
| `required()` | — | Označit jako povinné |
| `live()` | — | Spustit Livewire update při změně |

Label, hint, tooltip a další sdílené metody viz [Společné API pole](index.md#spolecne-api-pole).

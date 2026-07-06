---
order: 20
---

# Callout

Jemný, barevný upozorňovací box s volitelným nadpisem, ikonou a tlačítkem zavření.
Obsah těla pochází z dětských komponent (schéma) nebo z prostého řetězce přes
`content()`.

```php
use NyonCode\WireCore\Foundation\Schema\Callout;
```

## Použití

```php
Callout::make()
    ->warning()
    ->icon('exclamation-triangle')
    ->heading('Heads up')
    ->content('This action cannot be undone.')
```

S dětskými komponentami místo řetězcového těla:

```php
Callout::make()
    ->info()
    ->heading('Billing')
    ->schema([
        Placeholder::make('plan')->content('Pro'),
    ])
```

## Barvy

Barvy delegují na kanonickou alert paletu. Použijte sémantické zkratky nebo nastavte
libovolnou registrovanou barvu přímo:

```php
Callout::make()->info();      // ->color('info')
Callout::make()->success();
Callout::make()->warning();
Callout::make()->danger();
Callout::make()->color('primary');
```

## Zavíratelné

```php
Callout::make()->danger()->dismissible()->content('...')
```

## Metody

| Metoda | Popis |
|--------|-------------|
| `heading(string\|Closure)` | Tučný nadpis nad tělem |
| `title(string\|Closure)` | Alias pro `heading()` |
| `content(string\|Closure)` | Řetězcové tělo (alternativa k dětskému schématu) |
| `color(string\|Color)` | Nastavit odstín barvy (výchozí `info`) |
| `info()` / `success()` / `warning()` / `danger()` | Zkratky barev |
| `icon(string\|Icon)` | Ikona vykreslená vedle nadpisu |
| `dismissible(bool)` | Zobrazit tlačítko zavření |

## Samostatný tag

Stejná komponenta je dostupná jako Blade tag mimo schéma:

```blade
<x-wire::callout color="warning" heading="Heads up">
    This action cannot be undone.
</x-wire::callout>
```

Ve formulářích je zobrazovací pole [Alert](../../forms/fields/alert.md)
field-style aliasem této komponenty.

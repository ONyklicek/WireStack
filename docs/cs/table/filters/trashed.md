---
order: 31
nav: false
---

# TrashedFilter

Přepíná dotaz mezi živými, měkce smazanými a všemi záznamy.

```php
use NyonCode\WireTable\Filters\TrashedFilter;
```

Na rozdíl od ostatních filtrů neomezuje žádný sloupec: mění, který globální scope
platí — mapuje se na `withTrashed()` / `onlyTrashed()`, ne na klauzuli where.

## Základní použití

```php
Table::make()
    ->filters([
        TrashedFilter::make('trashed'),
    ])
```

Tři stavy, z nichž jen dva jsou volbami — „bez smazaných" je placeholder, tedy
zrušení filtru:

| Stav | Dotaz |
|------|-------|
| *(prázdný)* | jen živé záznamy — platí výchozí scope |
| `with` | `withTrashed()` — živé i smazané dohromady |
| `only` | `onlyTrashed()` — jen smazané záznamy |

## Popisky

```php
TrashedFilter::make('trashed')
    ->label('Záznamy')
    ->withTrashedLabel('Včetně archivovaných')
    ->onlyTrashedLabel('Jen archivované')
```

## Obnovení z filtrovaného pohledu

Zkombinujte ho s řádkovou akcí viditelnou jen u smazaných záznamů:

```php
Table::make()
    ->filters([TrashedFilter::make('trashed')])
    ->actions([
        Action::make('restore')
            ->visible(fn ($record) => $record->trashed())
            ->action(fn ($record) => $record->restore()),
    ])
```

## Požadavky

Model tabulky musí používat `SoftDeletes`. Pokud ne, aplikace filtru vyhodí
`TableConfigurationException` s názvem filtru i modelu — místo aby selhala jako
nedefinovaná metoda `onlyTrashed()` hluboko v query builderu.

## TrashedFilter API

```php
->withTrashedLabel(?string $label)     // výchozí: „Včetně smazaných"
->onlyTrashedLabel(?string $label)     // výchozí: „Jen smazané"
->getWithoutTrashedLabel(): string     // placeholder, „Bez smazaných"
->options(array $options)              // vyhazuje výjimku, viz níže

TrashedFilter::WITH                    // 'with'
TrashedFilter::ONLY                    // 'only'
```

`options()` je zděděné ze `SelectFilter` a nedává tu smysl: tenhle filtr přepíná
scope měkkého mazání, místo aby porovnával sloupec s hodnotou, takže libovolná
volba nemá co dělat. Vlastní `getOptions()` přepíše cokoli nastaveného, takže
setter vypadal přijatě a přitom nic neměnil — nyní vyhodí
`TableConfigurationException` a odkáže na `withTrashedLabel()` a
`onlyTrashedLabel()`, což jsou dvě věci, které změnit *lze*.

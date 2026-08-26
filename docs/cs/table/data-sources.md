---
order: 70
---

# Zdroje dat

Tabulka čte řádky přes **zdroj dat**. Ve výchozím stavu ten zdroj obaluje
Eloquent builder a nikdy ho neuvidíte — `->model()` a `->query()` fungují přesně
jako dosud. Vlastní zdroj deklarujete, když řádky nejsou v databázi: read model,
seznam DTO, odpověď API.

## Mechanismus

Čtení tabulky jsou tři otázky a zdroj odpovídá na všechny: *které řádky
odpovídají* (`count`), *tahle jejich stránka* (`paginate`) a *řádek za tímhle
klíčem* (`resolveRecord`). Zdroj dostane `QueryPlan` — hledání, filtry, řazení
a joiny, které tabulka vyřešila ze stavu — a `PagingRequest` pro výřez.

Plán je deklarativní. Filtr je sloupec, operátor a hodnota, nikdy closure, a
právě to dělá zdroj mimo databázi vůbec možným: umí plán přečíst a odpovědět na
něj metodami `Collection` místo SQL.

Na co zdroj odpovědět neumí, to **deklaruje, že neumí** — a engine pak vyhodí
výjimku místo toho, aby vrátil řádky, které tiše ignorovaly půlku dotazu:

```php
public function capabilities(): CapabilitySet
{
    return new CapabilitySet(
        Capability::Filterable,
        Capability::Sortable,
        Capability::Paginable,
    );   // žádné SqlExpression, žádné Joinable — dotaz na ně vyhodí výjimku
}
```

To je celá bezpečnostní vlastnost. Tabulka nad API, které neumí řadit, to musí
říct — tabulka, která řadí podle ničeho a vypadá seřazeně, je horší než ta, která
zahlásí chybu.

## Tabulka nad řádky v paměti

```php
use NyonCode\WireTable\Data\CollectionDataSource;

public function table(Table $table): Table
{
    return $table
        ->dataSource(new CollectionDataSource([                       // [tl! focus]
            ['id' => 1, 'name' => 'Ada',   'score' => 90],            // [tl! focus]
            ['id' => 2, 'name' => 'Grace', 'score' => 70],            // [tl! focus]
        ]))                                                           // [tl! focus]
        ->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('score')->sortable(),
        ]);
}
```

Žádné `->model()`, žádné `->query()`. Hledání, filtry, řazení i stránkování
fungují; zdroj na ně odpoví nad polem.

## Co tabulka nad kolekcí neumí

`CollectionDataSource` deklaruje čtyři schopnosti a zbytek odmítá. Každé
odmítnutí vyhodí `UnsupportedQueryAspectException` se jménem aspektu:

| Když chcete | Odkud to přijde | Výsledek |
|-------------|-----------------|----------|
| Raw SQL výraz | `Column::sortUsing()` se SQL, `->sqlExpression()` | vyhodí `[sql_expression]` |
| Cestu přes relaci | `TextColumn::make('company.name')` | vyhodí `[joinable]` |
| Agregace přes subquery | rollupy `->sums()`, `->counts()` | vyhodí `[aggregateable]` |
| Cursor stránkování | `->paginationMode('cursor')` | vyhodí `[cursor paging]` |
| Detekci změny pro polling | `->poll()` | vrátí `null`; polling místo toho porovná řádky |

Nic z toho nejsou omezení, která by se dala tiše obejít. Tabulka nad seznamem
v paměti je **omezená tabulka** a to omezení je vidět hned při první žádosti
o něco mimo něj.

## Vlastní zdroj

Implementujte `NyonCode\WireCore\Core\Data\DataSource`. Každá metoda dostane
plán, takže zdroj rozhoduje, kolik z něj splní:

```php
use NyonCode\WireCore\Core\Data\DataSource;

final class ReportingApiSource implements DataSource
{
    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginator|Paginator|CursorPaginator;
    public function get(QueryPlan $plan): Collection;
    public function chunk(QueryPlan $plan, int $size, callable $callback): void;   // [tl! focus]
    public function count(QueryPlan $plan): int;
    public function resolveRecord(int|string $key): ?RecordContract;
    public function resolveRecords(array $keys): Collection;
    public function capabilities(): CapabilitySet;
    public function changeToken(QueryPlan $plan): ?string;   // [tl! focus]
}
```

Dvě z nich stojí za vysvětlení, protože jejich tvar není samozřejmý:

**`chunk()` existuje odděleně od `get()`**, aby export sta tisíc řádků zůstal
ohraničený v paměti. `get()` materializuje; `chunk()` streamuje a zastaví se
dřív, když callback vrátí `false`.

**`changeToken()` smí vrátit `null`** a null je skutečná odpověď, ne selhání:
znamená, že zdroj nemá levný způsob, jak zjistit, že se data pohnula — polling
tedy porovná řádky sám místo zkratky přes token.

## Záznamy

Zdroj vrací řádky jako `RecordContract` — `getKey()`, `get('dot.path')`,
`toArray()`, `unwrap()`. Ve stacku jsou dvě implementace: `EloquentRecord` obaluje
model, `ArrayRecord` pole.

**Váš vlastní kód dál dostává modely.** Framework rozbaluje na hranici, takže
closure akce si drží svoji obvyklou signaturu:

```php
Action::make('archive')
    ->action(fn (Model $record) => $record->archive());
```

Důsledek stojí za to říct rovnou: nad zdrojem, kde není co rozbalit, taková akce
k dispozici není. Je to táž degradace, jakou uplatňuje dotazová strana, jen
o úroveň výš.

## Co zůstává jen pro Eloquent

Dvě cesty si záměrně nechávají builder místo kontraktu a obě jsou případy, které
kontrakt vyjádřit neumí:

- **Rollupy nad výběrem** replikují agregační subquery na klíčovaný dotaz.
- **Zápisy fill handlu** berou pesimistický zámek řádku (`SELECT … FOR UPDATE`).

Vlastní zdroj nedostane ani jedno.

## Viz také

- [Exporty](exports.md) — streamují přes `chunk()` zdroje
- [Souhrny](summaries.md) — agregace, které ne-SQL zdroj odmítne
- [Výběr](selection.md) — hromadné akce a `resolveRecords()`

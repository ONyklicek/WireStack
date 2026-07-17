---
order: 21
nav: false
---

# Cesty relací a tečková notace

Názvy sloupců podporují hlubokou tečkovou notaci pro relace, agregáty, pivoty a morphy. Core parser Relation AST automaticky určuje JOINy, eager loady a podotázky.

## Jednoduchá relace

```php
TextColumn::make('author.name')       // belongsTo nebo hasOne
TextColumn::make('category.title')
```

## Vnořené relace

```php
TextColumn::make('author.country.name')        // 3 úrovně hluboko
TextColumn::make('order.customer.company.name') // 4 úrovně hluboko
```

## Agregáty

```php
TextColumn::make('orders.count')               // withCount
TextColumn::make('items.sum.amount')           // withSum
TextColumn::make('ratings.avg.score')          // withAvg
TextColumn::make('bids.min.amount')            // withMin
TextColumn::make('bids.max.amount')            // withMax
```

## Pivot data

```php
TextColumn::make('tags.pivot.sort_order')
TextColumn::make('roles.pivot.assigned_at')->dateTime()
```

## Morph relace

```php
TextColumn::make('commentable.title')          // polymorfní
```

## Řazení a filtrování podle singulární relace

Singulární relace — `belongsTo`, `hasOne` nebo `hasOneThrough` — se řeší `LEFT
JOINem`, takže řazení i filtrování proběhne v SQL proti přijoinované tabulce: žádná
`whereHas` podotázka, žádné filtrování v paměti. Vnořené singulární řetězce se
joinují segment po segmentu (`company.country.name` → dva zřetězené joiny) a
`hasOneThrough` se sám rozpadne na dva joiny (base → mezitabulka → cíl).

```php
// Řazení: označ relační sloupec jako sortable.
TextColumn::make('company.name')->sortable();

// Filtrování: SelectFilter podporuje tečkovou cestu přímo ve jméně.
SelectFilter::make('company.name')
    ->options(['Acme' => 'Acme', 'Globex' => 'Globex']);

// Ekvivalent, pokud chceš mít klíč stavu filtru plochý ('company') a přitom
// cílit na relační sloupec:
SelectFilter::make('company')
    ->column('company.name')
    ->options(['Acme' => 'Acme', 'Globex' => 'Globex']);
```

Obojí se přeloží na stejný join:

```sql
select "users".*
from "users"
left join "companies" as "users_company" on "users"."company_id" = "users_company"."id"
where "users_company"."name" = ?          -- filtr
order by "users_company"."name" asc       -- řazení
```

Takto se joinují jen singulární relace (`belongsTo`, `hasOne`, `hasOneThrough`).
To-many relace — `hasMany`, `belongsToMany`, `hasManyThrough`, `morphMany` — a morph
cíle se pro zobrazení eager-loadují a přes join se řadit ani filtrovat **nedají**
(join by násobil řádky rodiče).

### Scopes a constraints relace (vč. soft delete)

Přijoinovaná strana odpovídá tomu, co vrátí Eloquentova relační query. Relace
nesoucí jakýkoli constraint — **global scopes** modelu (`SoftDeletes`, tenancy,
published/active flag, cokoli přes `addGlobalScope()`) **nebo** constraints
deklarované přímo na metodě relace (`belongsTo(...)->where('active', true)`) — se
přijoinuje jako scoped subquery:

```sql
left join (
  select * from "companies"
  where "companies"."deleted_at" is null       -- SoftDeletes global scope
    and "companies"."active" = ?               -- ->where(...) na relaci
) as "users_company" on "users"."company_id" = "users_company"."id"
```

`LEFT JOIN` zůstává `LEFT JOINem`: rodič, jehož related řádek je scopem odfiltrován,
se pořád zobrazí — related hodnota se bere jako prázdná (řadí/filtruje se jako
`NULL`). U `hasOneThrough` se scopuje mezitabulka i cílový model. Relace bez scopes
a constraints si drží prostý přímý join na tabulku.

> Meze: constraints na metodě relace platí pro `belongsTo`/`hasOne`, ale ne pro
> `hasOneThrough` (tam se aplikují jen global scopes jeho modelů). Constraint musí
> být samostatný — korelovaný na rodičovský řádek
> (`whereColumn('companies.x', 'users.y')`) se jako subquery vyjádřit nedá.
> `morphOne` se přes join nescopuje (eager-loaduje se pro zobrazení).

## Jak to funguje

1. `RelationPath::parse('author.country.name')` vyprodukuje `[RelationSegment('author'), RelationSegment('country'), ColumnSegment('name')]`
2. `QueryPlanner` postaví `RelationGraph` určující optimální strategii přístupu
3. Singulární `belongsTo` / `hasOne` / `hasOneThrough` relace → LEFT JOIN (umožňuje řazení **i filtrování**; `hasOneThrough` přes dva joiny s mezitabulkou)
4. HasMany/belongsToMany/hasManyThrough/morphMany → eager load (jen zobrazení)
5. Agregáty → `withCount()` / `withSum()` podotázky
6. Pivot → JOIN mezitabulky

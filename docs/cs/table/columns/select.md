---
order: 23
nav: false
---

# SelectColumn

Inline select dropdown — ukládá okamžitě při změně.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

## Základní použití

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

## Options z relace

```php
SelectColumn::make('category_id')
    ->relationship('category', 'name')   // načíst options ze souvisejícího modelu
```

Související seznam je pro každý řádek stejný, takže se načte **jednou za render**
a znovu použije — ne jednou za buňku. Explicitní `->options()` má přednost.

```php
// Naplnit seznam sám ze známého záznamu (zřídka potřeba).
SelectColumn::make('category_id')
    ->relationship('category', 'name')
    ->loadRelationshipOptions($record)
```

## Options z enumu

Předejte třídu PHP enumu pro rozvinutí jeho case na options `value => label`. Labely pocházejí z
`getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`, jinak se
z názvu case udělá headline. Kontrakty viz [Enum a JSON casty](casts.md).

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Vždy nativní select

Editovatelná buňka vždy renderuje nativní `<select>` prohlížeče a žádný přepínač
`->native()` nenabízí. Je to jediná select plocha, která **nesdílí** combobox používaný ve
[`SelectFilter`](../filters/select.md), [`TernaryFilter`](../filters/ternary.md) a `Select`
fieldu ve formulářích: buňka commituje přes `wireEditableCell` (bind přes `x-model`,
uložení při změně), ne přes entangled statePath, což je jediný binding, který sdílený
combobox umí.

Pokud potřebuješ vyhledávatelný dropdown, použij [`SelectFilter`](../filters/select.md)
pro filtrování, nebo `Select` field ve formuláři uvnitř [edit akce](../actions.md)
pro editaci.

## Podmíněné disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // super admina nelze změnit
```

## API SelectColumn

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] nebo třída enumu
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
->relationship(string $name, string $titleAttribute)  // options z relace, načtené jednou za render
->loadRelationshipOptions(Model $record)             // naplnit seznam explicitně
```

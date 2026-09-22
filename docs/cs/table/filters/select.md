---
order: 31
summary: Rozbalovací seznam nad předdefinovanými možnostmi — filtr, po kterém většina tabulek sáhne první.
---

# SelectFilter

Rozbalovací filtr pro předdefinované možnosti. Nejběžnější typ filtru.

Vykresluje se přes stejný combobox jako `Select` field ve formulářích, takže
otevřený filtr vypadá stejně jako otevřený formulářový select. Nativní `<select>`
prohlížeče je opt-in přes [`->native()`](#nativni-html-select).

```php
use NyonCode\WireTable\Filters\SelectFilter;
```

## Základní použití

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ])
```

## S placeholderem

První položka je vždy prázdná možnost „All“. Pro přizpůsobení:

```php
SelectFilter::make('role')
    ->options([
        '' => 'All Roles',           // explicitní placeholder
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
```

## Vícenásobný výběr

```php
SelectFilter::make('tags')
    ->options(Tag::pluck('name', 'id')->toArray())
    ->multiple()
    ->label('Tags')
```

Při `multiple()` aplikuje `whereIn()` místo `where()`.

## Vyhledávatelný rozbalovací seznam

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Přidá do rozbalovacího seznamu vyhledávací input. Povrch je stejný combobox jako u
nevyhledávatelného filtru — jen navíc s vyhledávacím inputem.

## Nativní HTML select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // nativní <select> element prohlížeče (rychlejší render)
```

Odhlásí filtr ze sdíleného comboboxu, takže přestane odpovídat formulářovému
selectu. Používejte jen tam, kde je cena renderu důležitější než jednotný vzhled.

`->nativeOnMobile()` ponechá combobox od mobilního breakpointu filtru výš a pod
ním vykreslí `<select>` prohlížeče, kde telefon místo sheetu otevře vlastní výběr:

```php
SelectFilter::make('type')
    ->options([...])
    ->nativeOnMobile()               // telefon: nativní <select>; desktop: combobox
```

Volba platí v panelu filtrů i v řádku filtrů v hlavičce sloupců — oba vykreslují
stejný prvek, včetně filtru s `multiple()`. `->touchOnMobile()` místo toho
otevře [dotykový seznam](../../forms/fields/select.md#dotykovy-seznam-na-telefonu),
na obou místech. `->native()` nad ní vyhrává; výchozí hodnota pro celou aplikaci je
`wire-core.mobile.native` (viz [mobil](../../start/configuration.md#mobil)).

## Z databáze

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Možnosti mohou být closure — vyhodnocují se líně při renderu.

## Z enumu

Předejte třídu PHP enumu místo pole — jeho case se rozvinou na možnosti `value => label`.
Popisky pocházejí z `getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`,
jinak se z názvu case udělá headline.

```php
SelectFilter::make('status')->options(OrderStatus::class)
```

> Když je atribut modelu **přetypován** na enum, `SelectFilter` na tom sloupci
> automaticky naplní své možnosti z enumu i bez volání `->options()`. Tato zkratka je
> pro případy, kdy filtr nastavujete explicitně.

## Vlastní dotaz

```php
SelectFilter::make('has_avatar')
    ->options([
        'yes' => 'With Avatar',
        'no' => 'Without Avatar',
    ])
    ->query(fn (Builder $query, string $value) => match($value) {
        'yes' => $query->whereNotNull('avatar_url'),
        'no' => $query->whereNull('avatar_url'),
    })
```

## API SelectFilter

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] nebo třída enumu
->multiple(bool $multiple = true)    // režim multi-select
->searchable(bool $searchable = true) // přidá vyhledávací input do rozbalovacího seznamu
->native(bool $native = true)        // opt-in nativní <select> prohlížeče (výchozí: false)
->nativeOnMobile(bool $condition = true) // nativní <select> jen pod mobilním breakpointem (výchozí: config wire-core.mobile.native)
->touchOnMobile(bool $condition = true)  // dotykový seznam přes celou výšku pod mobilním breakpointem (výchozí: config wire-core.mobile.touch)
```

---
order: 31
nav: false
---

# SelectFilter

Dropdown filtr pro předdefinované options. Nejběžnější typ filtru.

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

První položka je vždy prázdná option „All". Pro přizpůsobení:

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

## Vyhledávatelný dropdown

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Přidá do dropdownu vyhledávací input. Povrch je stejný combobox jako u
nevyhledávatelného filtru — jen navíc s vyhledávacím inputem.

## Nativní HTML select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // nativní <select> element prohlížeče (rychlejší render)
```

Odhlásí filtr ze sdíleného comboboxu, takže přestane odpovídat formulářovému
selectu. Používej jen tam, kde je cena renderu důležitější než jednotný vzhled.

## Z databáze

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Options mohou být Closure — vyhodnoceno lazy při renderu.

## Z enumu

Předejte třídu PHP enumu místo pole — jeho case se rozvinou na options `value => label`.
Labely pocházejí z `getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`,
jinak se z názvu case udělá headline.

```php
SelectFilter::make('status')->options(OrderStatus::class)
```

> Když je atribut modelu **přetypován** na enum, `SelectFilter` na tom sloupci
> auto-naplní své options z enumu i bez volání `->options()`. Tato zkratka je
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
->searchable(bool $searchable = true) // přidá vyhledávací input do dropdownu
->native(bool $native = true)        // opt-in nativní <select> prohlížeče (výchozí: false)
```

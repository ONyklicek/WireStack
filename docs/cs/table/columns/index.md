---
order: 20
---

# Sloupce

Wire Table poskytuje **16 typů sloupců**. Všechny sdílejí stejné základní API
sloupce pro popisky, viditelnost, autorizaci, řazení, formátování a inline
editaci — dokumentované níže. Typ vyberte podle vykreslení buňky; sdílené API
sáhněte na kterýkoli z nich.

## Typy sloupců

| Sloupec | Použití pro |
|--------|---------|
| [TextColumn](text.md) | Univerzální text s presety formátování data/měny/čísel |
| [BadgeColumn](badge.md) | Status pilulky s barvou a ikonou, vč. self-coloringu enumů |
| [BooleanColumn](boolean.md) | True/false jako ikona (fajfka / křížek) |
| [IconColumn](icon.md) | Ikony podle stavu nebo dynamicky resolvované |
| [ImageColumn](image.md) | Avatary a náhledy |
| [ButtonColumn](button.md) | Tlačítko s odkazem nebo Livewire akcí v buňce |
| [ToggleColumn](toggle.md) | Inline editovatelný přepínač on/off |
| [CheckboxColumn](checkbox.md) | Inline editovatelné zaškrtávátko (hustší ToggleColumn) |
| [SelectColumn](select.md) | Inline editovatelný dropdown (options, relace, enumy) |
| [TextInputColumn](text-input.md) | Inline editovatelný text/číslo/email input |
| [StackedColumn](stacked.md) | Layouty avatar + jméno + email na sobě |
| [SplitColumn](split.md) | Poskládat několik sloupců vedle sebe |
| [PollColumn](poll.md) | Buňky se živě pollovaným stavem/postupem |
| [ColorColumn](color.md) | Uložená CSS barva jako vzorník |
| [RatingColumn](rating.md) | Číselné hodnocení jako hvězdičky |
| [TagsColumn](tags.md) | Vícehodnotový stav jako chipsy |

## Koncepty

- [Cesty relací a tečková notace](relations.md) — zobrazit hodnoty souvisejících modelů, agregáty, pivoty
- [Enum a JSON casty](casts.md) — labely/barvy/ikony enumů a rendering array/json
- [Editace a filtry na úrovni sloupce](editing.md) — inline editace a per-sloupcové inputy filtrů
- [Fill handle](fill-handle.md) — vyplňování tažením jako v Excelu, jedním requestem
- [Vzory a recepty](patterns.md) — kompletní příkladové tabulky

## Sdílené API sloupce

Každý sloupec dědí tyto schopnosti ze základní třídy `Column`.

### Factory a identita

```php
Column::make(string $name)           // statická factory — $name je cesta v tečkové notaci
->label(string|Closure $label)        // zobrazovací popisek v <th> (auto-generovaný z názvu)
->getName(): string                   // získat název sloupce
->getLabel(): string                  // získat resolvovaný popisek
```

### Řazení

```php
->sortable(bool $sortable = true, ?Closure $query = null)
->isSortable(): bool

// Vlastní logika řazení
->sortUsing(Closure $fn)
```

```php
TextColumn::make('full_name')
    ->sortable()
    ->sortUsing(function (Builder $query, string $direction) {
        $query->orderBy('last_name', $direction)
              ->orderBy('first_name', $direction);
    })
```

### Hledání

```php
->searchable(bool|array $searchable = true)
->isSearchable(): bool

// Předejte pole pro hledání v konkrétních DB sloupcích (když je název sloupce virtuální)
->searchable(['first_name', 'last_name', 'email'])

// Vlastní logika hledání
->searchUsing(Closure $fn)

// Deklarovat, co sloupec drží, aby šlo do hledání psát >100 a 10..20
->searchAs(SearchValueType|string $type)      // 'text' | 'numeric' | 'date' | 'code'

// Získat resolvované sloupce hledání
->getSearchColumns(): array
```

> `searchColumns(array $columns)` jako samostatný setter existuje jen na `StackedColumn`. Na ostatních sloupcích předejte pole rovnou do `searchable()`.

```php
// Hledat napříč více DB sloupci
TextColumn::make('user')
    ->searchable(['first_name', 'last_name', 'email'])

// Vlastní logika hledání
TextColumn::make('full_name')
    ->searchable()
    ->searchUsing(function (Builder $query, string $search) {
        $query->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
    })
```

`searchAs()` má smysl teprve tehdy, když tabulka zapne
[hledání rozsahů](../overview.md#syntaxe-hledani). Typ hodnoty se obvykle odvodí
z castů modelu — cast `decimal:2` nebo `datetime` stačí — deklarujte ho tedy jen
tam, kde za sloupec casty mluvit nemohou:

```php
// Model nemá pro `amount` žádný cast, takže se z něj nedá nic odvodit.
TextColumn::make('amount')
    ->searchable()
    ->searchAs('numeric')      // ">1000" a "10..20" se teď dostanou i na tento sloupec
```

Sloupec ponechaný jako text porovnání přeskočí, místo aby porovnával
lexikograficky — chybná nebo chybějící deklarace tak jen zúží, čemu hledání
rozumí, nikdy nevrátí špatné řádky.

Samotná deklarace nic nezapíná. Hledatelný sloupec, který typ deklaruje, zatímco
hledání tabulky rozsahy nečte, se při renderu tabulky odmítne a pojmenuje
chybějící volání — jinak by se tabulka vrátila prázdná, protože `10..20` by se
hledalo jako doslovný text.

`'code'` je jediný typ, který se **nikdy** neodvozuje: říká, že hodnota je řada
plus číslo **doplněné nulami** (`8866 01`, `8866 02`), což je právě to, co dělá
porovnání textem správným — a ví to jen vlastník. Odemyká
[rozsahy uvnitř řady](../overview.md#rozsahy-uvnitr-strukturovaneho-kodu) —
`8866 01..08`.

### Viditelnost a přepínatelnost

```php
->hidden(bool|Closure $hidden = true)        // skrýt sloupec
->isHidden(): bool

// Přepínatelné uživatelem (výběr sloupců)
->toggleable(bool $toggleable = true)

// Podle oprávnění
->permission(?string $permission)            // viditelné jen když má uživatel oprávnění
->visible(Closure $callback)                 // vlastní callback viditelnosti (jen Closure)

// Viditelnost buňky per záznam (redakce jedné buňky per řádek)
->visibleForRecord(Closure $callback)        // fn ($record) => bool
```

`->hidden()`, `->permission()`, `->visible()` a `->authorize()` rozhodují, zda
sloupec v tabulce vůbec existuje — vyhodnocují se **jednou, bez záznamu** (řídí
také hlavičku, přepínání sloupců a export). Pro skrytí nebo redakci **jedné buňky
per řádek** — např. zobrazit `salary` jen pro záznamy, které uživatel smí vidět —
použijte `->visibleForRecord(fn ($record) => …)`, který běží při renderu buňky se
záznamem řádku. Skrytá buňka se vykreslí prázdná; sloupec dál zabírá své místo
v každém dalším řádku.

```php
TextColumn::make('salary')
    ->visibleForRecord(fn ($record) => auth()->user()->can('viewSalary', $record));
```

### Responzivní breakpointy

```php
->visibleFrom(string $breakpoint)      // skryté pod tímto breakpointem
->hiddenFrom(string $breakpoint)       // skryté od tohoto breakpointu nahoru
->onlyOnMobile()                       // viditelné jen na mobilu (<md)
->onlyOnDesktop()                      // viditelné jen na desktopu (≥lg)
->onlyOnTabletAndUp()                  // viditelné od md nahoru
->onlyOnLargeScreens()                 // viditelné od xl nahoru
```

```php
TextColumn::make('phone')
    ->visibleFrom('md')          // skryté na mobilu, viditelné od md

TextColumn::make('notes')
    ->onlyOnLargeScreens()       // viditelné jen na xl+
```

### Responzivní varianty zobrazení

```php
// Vlastní render pro mobil vs desktop
->mobileDisplayUsing(Closure $fn)
->desktopDisplayUsing(Closure $fn)
->hasResponsiveDisplay(): bool

// Kam sloupec padne na skládané mobilní kartě (viz Pokročilé → Responzivní rozvržení)
->mobileTitle() ->mobileSubtitle() ->mobileMetric() ->mobileMeta() ->mobileDetail()
```

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} <{$record->email}>")
```

### Formátování hodnot

```php
->formatStateUsing(Closure $fn)        // transformovat hodnotu pro zobrazení
->displayUsing(Closure $fn)            // alias pro formatStateUsing
->default(mixed $value)                // hodnota, když je stav null
->placeholder(string $text)            // text zobrazený, když je hodnota null/prázdná
->limit(int $chars)                    // zkrátit na N znaků
->prefix(string $prefix)              // předřadit text
->suffix(string $suffix)              // přidat text
->html(bool $html = true)             // vykreslit hodnotu jako raw HTML
->wrap(bool $wrap = true)             // povolit zalamování textu (výchozí: nowrap)
```

```php
TextColumn::make('price')
    ->prefix('$')
    ->suffix(' USD')
    ->placeholder('N/A')

TextColumn::make('bio')
    ->limit(100)
    ->tooltip(fn ($record) => $record->bio)   // zobrazit celé při hoveru

TextColumn::make('content')
    ->html()
    ->wrap()
    ->limit(200)
```

### Stylování textu

Použijte `->textSize()` pro **velikost písma** buňky. `->size()` (ze sdíleného concernu `HasSize`) nastaví *strukturální* velikost sloupce a **nemění** písmo textu.

```php
->textSize(string $size)               // 'xs', 'sm', 'md', 'lg', 'xl' — velikost písma textu
->weight(string $weight)              // 'thin', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold'
->textColor(string $color)            // název Tailwind barvy nebo 'gray', 'primary', atd.
->fontFamily(string $family)          // 'sans', 'serif', 'mono' (jen TextColumn)
```

```php
TextColumn::make('name')
    ->weight('bold')
    ->textSize('lg')

TextColumn::make('subtitle')
    ->textSize('sm')
    ->textColor('gray')
    ->weight('light')
```

### Šířka a zarovnání

```php
->width(string $width)                 // CSS šířka: '200px', '20%', 'auto'
->alignment(string $alignment)         // 'left', 'center', 'right'
->alignLeft()                          // zkratka
->alignCenter()                        // zkratka
->alignRight()                         // zkratka
```

### Ikony

```php
->icon(string|Icon|null $icon, ?string $position = 'before')   // pozice: 'before' | 'after'
->color(string|Color $color)           // statická barva ikony/textu (pro barvu per řádek použijte BadgeColumn/IconColumn colorUsing())
```

```php
TextColumn::make('email')
    ->icon('mail', 'before')
    ->color('primary')
```

### URL (klikatelná buňka)

```php
->actionUrl(Closure $url, bool $openInNewTab = false)   // udělat z buňky odkaz
```

```php
TextColumn::make('name')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
    ->color('primary')
```

### Kopírovatelné

```php
->copyable(bool $copyable = true)      // ikona kopírování kliknutím
->copyMessage(string $msg)             // text zpětné vazby po zkopírování
```

### Tooltip a popis

```php
->tooltip(string|Closure $tooltip)     // tooltip při hoveru
->description(string|Closure $desc)    // sekundární text pod hodnotou
```

```php
TextColumn::make('title')
    ->description(fn ($record) => Str::limit($record->body, 50))
    ->tooltip(fn ($record) => "Created: {$record->created_at->format('d.m.Y')}")
```

### Souhrn (agregátní patička)

```php
->summarize(string $aggregate, ?string $label = null)
```

Dostupné agregáty: `'sum'`, `'avg'`, `'count'`, `'min'`, `'max'`, `'range'`

Detaily viz [Pokročilé — Souhrn](../advanced.md#souhrnna-paticka-agregaty).

### Extra HTML atributy

```php
->extraAttributes(array $attrs)        // na <td>
->extraHeaderAttributes(array $attrs)  // na <th>
```

```php
TextColumn::make('notes')
    ->extraAttributes(['data-testid' => 'notes-cell'])
    ->extraHeaderAttributes(['class' => 'bg-gray-100'])
```

### Pivot sloupce

```php
->pivot(bool $isPivot = true)          // označí jako sloupec pivot tabulky
->isPivot(): bool
```

Pro many-to-many relace s pivot daty:
```php
TextColumn::make('roles.pivot.assigned_at')
    ->pivot()
    ->dateTime('d.m.Y')
```

### Přístup ke stavu

```php
->state(mixed $value)                  // přepsat hodnotu stavu
->getState(Model $record): mixed       // resolvovat stav ze záznamu
```

### Vlastní rendering (Blade partialy)

Každý sloupec vlastní svůj **stav/konfiguraci** a deleguje **markup** na Blade
partial pod `packages/table/resources/views/tables/columns/`. Základní textová
buňka se vykresluje přes `text.blade.php`; každý sloupec s custom-UI má svůj
vlastní partial (`badge`, `boolean`, `icon`, `image`, `button`, `toggle`, `poll`,
`split`, `stacked`, `select`, `text-input-*`). Sloupce nikdy nevrací inline HTML
z `renderCell()` — volají `renderView('tables.columns.<name>', [...])`.

Dva způsoby přizpůsobení markupu:

```php
// 1. Přepis per sloupec — nasměrujte jakýkoli sloupec na svůj vlastní Blade pohled.
TextColumn::make('name')->view('columns.my-name-cell');

// 2. Přepis pro celý projekt — publikujte pohledy balíčku a upravte partial.
//    php artisan vendor:publish --tag=wire-table::views
//    pak upravte resources/views/vendor/wire-table/tables/columns/badge.blade.php
```

Pořadí resolvování pohledu: explicitní `->view()` vyhrává, pak pohled balíčku
(`wire-table::tables.columns.<name>`), pak app-level pohled stejného názvu. Váš
partial dostane přesně ta data jako vestavěný — už resolvované primitivy
stavu/konfigurace pro daný sloupec — takže přepisujete jen HTML.

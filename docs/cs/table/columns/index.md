---
order: 20
summary: Všechny typy sloupců a základní API, které sdílejí — popisky, viditelnost, autorizace, řazení, formátování a inline editace.
---

# Sloupce

Wire Table poskytuje **19 typů sloupců**. Všechny sdílejí stejné základní API
sloupce pro popisky, viditelnost, autorizaci, řazení, formátování a inline
editaci — dokumentované níže. Typ vyberte podle vykreslení buňky; sdílené API
sáhněte na kterýkoli z nich.

## Typy sloupců

| Sloupec | Použití pro |
|--------|---------|
| [TextColumn](text.md) | Univerzální text s presety formátování data/měny/čísel |
| [BadgeColumn](badge.md) | Status pilulky s barvou a ikonou, vč. self-coloringu enumů |
| [MoneyColumn](money.md) | Částky, doprava a tabulárně; metrika stacked karty |
| [MetricColumn](metric.md) | Měření: agregované číslo s volitelnou čárou trendu |
| [PhoneColumn](phone.md) | Telefonní číslo zapsané ke čtení a odkázané k vytáčení |
| [BooleanColumn](boolean.md) | True/false jako ikona (fajfka / křížek) |
| [IconColumn](icon.md) | Ikony podle stavu nebo dynamicky resolvované |
| [ImageColumn](image.md) | Avatary a náhledy |
| [ButtonColumn](button.md) | Tlačítko s odkazem nebo Livewire akcí v buňce |
| [ToggleColumn](toggle.md) | Inline editovatelný přepínač on/off |
| [CheckboxColumn](checkbox.md) | Inline editovatelné zaškrtávátko (hustší ToggleColumn) |
| [SelectColumn](select.md) | Inline editovatelný rozbalovací seznam (možnosti, relace, enumy) |
| [TextInputColumn](text-input.md) | Inline editovatelný text/číslo/email input |
| [StackedColumn](stacked.md) | Layouty avatar + jméno + email na sobě |
| [SplitColumn](split.md) | Poskládat několik sloupců vedle sebe |
| [PollColumn](poll.md) | Buňky se živě pollovaným stavem/postupem |
| [ColorColumn](color.md) | Uložená CSS barva jako vzorník |
| [RatingColumn](rating.md) | Číselné hodnocení jako hvězdičky |
| [TagsColumn](tags.md) | Vícehodnotový stav jako chipsy |

## Koncepty

- [Cesty relací a tečková notace](relations.md) — zobrazit hodnoty souvisejících modelů, agregáty, pivoty
- [Enum a JSON casty](casts.md) — popisky/barvy/ikony enumů a rendering array/json
- [Editace a filtry na úrovni sloupce](editing.md) — inline editace a filtrovací inputy v jednotlivých sloupcích
- [Fill handle](fill-handle.md) — vyplňování tažením jako v Excelu, jedním requestem
- [Vzory a recepty](patterns.md) — kompletní příkladové tabulky

## Sdílené API sloupce

Každý sloupec dědí tyto schopnosti ze základní třídy `Column`.

### Factory a identita

```php
Column::make(string $name): static     // statická factory — $name je cesta v tečkové notaci
->label(string|Closure|null $label): static // zobrazovací popisek v <th> (automaticky generovaný z názvu)
->getName(): string                   // získat název sloupce
->getLabel(): string                  // získat resolvovaný popisek
```

### Řazení

```php
->sortable(bool $sortable = true, ?Closure $query = null): static
->isSortable(): bool
->getSortColumn(): ?string           // atribut, podle kterého hlavička řadí

// Vlastní logika řazení
->sortUsing(Closure $fn): static
```

`isSortable()` rozhoduje, jestli je hlavička klikací; `getSortColumn()` rozhoduje,
podle čeho ten klik řadí. U běžného sloupce jsou to tytéž řetězce — jeho vlastní
jméno včetně tečkové cesty přes relaci — takže ho nikdy nevoláte. Rozejdou se u
**složeného** sloupce: `SplitColumn` je zaregistrovaný pod jménem skupiny, kterou
kreslí, a odpovídá prvním řaditelným dítětem, které drží. Dotazový šev se ptá
sloupce místo toho, aby použil jméno z kliknutí — a to je to, co složené hlavičce
brání řadit podle atributu, který neexistuje.

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
// [tl! focus]
->searchable(bool|array $searchable = true): static
->isSearchable(): bool

// Předejte pole pro hledání v konkrétních DB sloupcích (když je název sloupce virtuální)
->searchable(['first_name', 'last_name', 'email'])

// Vlastní logika hledání
->searchUsing(Closure $fn): static

// Deklarovat, co sloupec drží, aby šlo do hledání psát >100 a 10..20
->searchAs(SearchValueType|string $type): static // 'text' | 'numeric' | 'date' | 'code'

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

Callback se volá jednou pro každé slovo výrazu — `jan novak` ho zavolá s `jan`
a pak s `novak` a řádek musí najít obě — protože hledání ve výchozím stavu dělí
podle mezer. Pod `->search(fn ($s) => $s->literal())` dostane celý výraz jednou.

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
->hidden(bool|Closure $hidden = true): static // skrýt sloupec
->isHidden(): bool

// Přepínatelné uživatelem (výběr sloupců)
->toggleable(bool $toggleable = true): static

// Podle oprávnění
->permission(?string $permission): static    // viditelné jen když má uživatel oprávnění
->authorize(?string $ability): static        // autorizovat přes Laravel Gate ability
// [tl! focus]
->authorizeUsing(?Closure $callback): static // fn ($user, $record = null) => bool
->visible(bool|Closure $condition = true): static // vlastní podmínka viditelnosti

// Viditelnost buňky pro každý záznam (redakce jedné buňky na řádek)
->visibleForRecord(Closure $callback): static // fn ($record) => bool
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
->visibleFrom(string|Breakpoint $breakpoint): static // skryté pod tímto breakpointem
->hiddenFrom(string|Breakpoint $breakpoint): static  // skryté od tohoto breakpointu nahoru
->onlyOnMobile(): static                 // viditelné jen pod md
->mobileOnly(): static                   // alias pro onlyOnMobile()
->onlyOnDesktop(): static                // viditelné od md nahoru
->desktopOnly(): static                  // alias pro onlyOnDesktop()
->onlyOnTabletAndUp(): static            // viditelné od sm nahoru
->onlyOnLargeScreens(): static           // viditelné od lg nahoru
->hasResponsiveVisibility(): bool
```

Enum `Breakpoint` přijímá `sm`, `md`, `lg`, `xl` a `2xl`; řetězce a enum
varianty jsou zaměnitelné. Tyto pomocníky nastaví responzivní třídy na buňce
a hlavičce, nemění dotaz ani sloupec neodstraňují.

```php
TextColumn::make('phone')
    ->visibleFrom('md')          // skryté na mobilu, viditelné od md

TextColumn::make('notes')
    ->onlyOnLargeScreens()       // viditelné jen na lg+
```

### Responzivní varianty zobrazení

```php
// Vlastní render pro mobil vs desktop
// [tl! focus]
->mobileDisplayUsing(Closure $callback): static
->desktopDisplayUsing(Closure $callback): static
->mobileBreakpoint(string|Breakpoint $breakpoint): static // přepíná na 'md' ve výchozím stavu
->hasResponsiveDisplay(): bool

// Kam sloupec padne na skládané mobilní kartě (viz Pokročilé → Responzivní rozvržení)
->mobileSlot(MobileSlot|string $slot): static // 'title' | 'subtitle' | 'metric' | 'meta' | 'detail'
->mobileTitle(): static
->mobileSubtitle(): static
->mobileMetric(): static
->mobileMeta(): static
->mobileDetail(): static
->getMobileSlot(): ?MobileSlot
```

Bez explicitního `mobileSlot()` odvodí skládaná karta slot z pořadí a zarovnání
sloupců. Volba slotu určuje hierarchii karty; neskrývá sloupec v žádném
breakpointu. `mobileDisplayUsing()` a `desktopDisplayUsing()` naopak nahrazují
zobrazovaný obsah na každé straně `mobileBreakpoint()`.

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} <{$record->email}>")
```

### Formátování hodnot

```php
->formatStateUsing(Closure $fn): static // transformovat hodnotu pro zobrazení
->displayUsing(Closure $fn): static     // alias pro formatStateUsing
->default(mixed $default): static       // fallback, když je resolvovaný stav null nebo prázdný
->getDefault(): mixed
->placeholder(string|Closure|null $placeholder): static // text zobrazený, když je hodnota null/prázdná
->getPlaceholder(): ?string
->limit(?int $chars): static            // zkrátit na N znaků
->prefix(?string $prefix): static       // předřadit text
->suffix(?string $suffix): static       // přidat text
->html(bool $html = true): static       // vykreslit hodnotu jako raw HTML
->wrap(bool $wrap = true): static       // povolit zalamování textu (výchozí: nowrap)
```

```php
TextColumn::make('price')   // [tl! focus:3]
    ->prefix("$")
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
->size(string|Size|Closure $size): static // strukturální velikost — 'xs'|'sm'|'md'|'lg'|'xl'; výchozí 'md'
->xs(): static
->sm(): static
->md(): static
->lg(): static
->xl(): static
->getSize(): string
->textSize(string $size): static          // velikost písma; přijímá velikostní tokeny Tailwindu
->getTextSize(): ?string
->weight(string|FontWeight $weight): static
->getTextWeight(): ?string
->textColor(string|Color $color): static
->getTextColor(): ?string
->fontFamily(?string $family): static     // 'sans', 'serif', 'mono' (jen TextColumn; null zruší)
->getFontFamily(): ?string
```

`size()` řídí strukturální velikost sloupce a výchozí je `md`; přijímá případy
enumu `Size`, řetězce i closures. Je nezávislá na `textSize()`, která řídí
typografii buňky. `weight()` přijímá kanonický enum `FontWeight` nebo řetězcový
token.

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
->width(string $width): static          // preferovaná CSS šířka
->minWidth(string $width): static       // minimální CSS šířka
->maxWidth(string $width): static       // maximální CSS šířka
->getWidth(): ?string
->getMinWidth(): ?string
->getMaxWidth(): ?string
->alignment(string|Alignment $alignment): static // 'left' | 'center' | 'right'; výchozí 'left'
->alignLeft(): static
->alignCenter(): static
->alignRight(): static
->getAlignment(): string
```

Všechny tři hodnoty šířky jsou volitelné a nezávislé. `width()` nastavuje
preferovanou šířku; `minWidth()` a `maxWidth()` ji omezují při rozvržení tabulky
v prohlížeči. Hodnoty se předávají jako CSS, nepřevádějí se na Tailwind třídy ani
nenormalizují, proto používejte platné CSS hodnoty jako délky, procenta, `auto`
nebo `min-content`. Deklarace se vykreslí na `<th>` daného sloupce; pokud nejsou
nastavené žádné z nich, hlavička nedostane žádný inline styl šířky a prohlížeč
použije běžné rozvržení tabulky.

```php
TextColumn::make('reference')
    ->width('9rem')
    ->minWidth('8rem')
    ->maxWidth('12rem');
```

### Ikony

```php
->icon(string|Icon|Closure|null $icon, ?string $position = 'before'): static // pozice: 'before' | 'after'
->color(string|Color|null $color): static // barva sloupce: text a ikona, pokud nemá vlastní
->iconColor(string|Color|Closure|null $color): static // barva ikony pro každý záznam
->iconTile(bool $tile = true): static   // posadit ikonu do tónované dlaždice
```

Na **seznamu** (`layout('list')`) sáhněte i po `iconTile()`: holá tónovaná ikona
stačí na mřížce sloupců, kde je řádek už tak řada zarovnaných hodnot — ale tam,
kde je záznam věta, dá teprve dlaždice řádkům levou hranu, po které oko sjíždí.
Pozadí i ikona přicházejí z jedné role, takže se nemohou rozejít, a dlaždici
dostanou jen sémantické role — surový odstín o *druhu* nic neříká, takže padne na
neutrální.

`color()` se resolvuje jednou pro celý sloupec, což je správně pro tón textu
a špatně pro **stavovou** ikonu, jejímž celým úkolem je lišit se řádek od řádku.
Na to je `iconColor()`: předejte roli ze sdíleného slovníku, nebo closure nad
záznamem. Closure se vzdá statického mema ikony — stejná cena, jakou už platí
closure v `icon()`, a důvod, proč ani jedno není výchozí.

```php
TextColumn::make('state')
    ->icon(fn ($record) => $record->failed ? 'x-circle' : 'check-circle')      // [tl! focus:start]
    ->iconColor(fn ($record) => $record->failed ? 'danger' : 'success')        // [tl! focus:end]

TextColumn::make('email')
    ->icon('mail', 'before')
    ->color('primary')
```

### URL (klikatelná buňka)

```php
->actionUrl(Closure $callback, bool $openInNewTab = false): static // udělat z buňky odkaz
->navigate(?bool $condition = true): static // true / false přebije wire-core.navigate; null se jím řídí
```

Odkaz na stránku této aplikace se otevře přes `wire:navigate`, když je zapnutý
`wire-core.navigate`; jiný web nebo nová záložka je obyčejný odkaz. Viz
[Konfigurace → Navigace](../../start/configuration.md#navigace).

```php
TextColumn::make('name')
    ->actionUrl(fn ($record) => route('users.show', $record), openInNewTab: true)
    ->color('primary')
```

### Kopírovatelné

```php
->copyable(bool $copyable = true, ?string $copyMessage = null): static // ikona kopírování kliknutím
->copyMessage(string $copyMessage): static // text zpětné vazby po zkopírování
->getCopyMessage(): ?string
```

### Tooltip a popis

```php
->tooltip(string|Closure|null $tooltip): static // tooltip při hoveru; null jej zruší
->getTooltip(): ?string
->description(string|Closure $description, string $position = 'below'): static // sekundární text a pozice
->getDescription(): string|Closure|null
```

```php
TextColumn::make('title')
    ->description(fn ($record) => Str::limit($record->body, 50))
    ->tooltip(fn ($record) => "Created: {$record->created_at->format('d.m.Y')}")
```

### Souhrn (agregátní patička)

```php
->summarize(string|Closure|SummaryType $type, ?string $label = null, string $scope = 'query', ?Closure $format = null, ?Closure $when = null): static
->summaryDecimals(int $decimals, string $decimalSeparator = ',', string $thousandsSeparator = ' '): static
```

Zkratky pro souhrny, rozsahy a úplný slovník agregací jsou uvedené v
[souhrnech](../summaries.md).

### Extra HTML atributy

```php
->extraAttributes(array|string $attributes): static // na <td>; array se escapuje, string je raw
->extraHeaderAttributes(array $attributes): static  // na <th>
```

U `extraAttributes()` upřednostněte variantu s array; string je důvěryhodný
markup, který musí escapovat volající.

```php
TextColumn::make('notes')
    ->extraAttributes(['data-testid' => 'notes-cell'])
    ->extraHeaderAttributes(['class' => 'bg-gray-100'])
```

### Pivot sloupce

```php
->pivot(bool $isPivot = true): static  // označí jako sloupec pivot tabulky
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
->state(Closure $callback): static     // fn (Model $record) => mixed
->getState(Model $record): mixed       // resolvovat stav ze záznamu
->getRawState(Model $record): mixed    // původní hodnota před formátováním zobrazení
```

### Eager loading hodnot z closure

Query planner odvodí relace z cesty sloupce jako `company.name`. Neumí
prohlédnout closure použité v `displayUsing()`, `actionUrl()` nebo callbacku
barvy, proto relace deklarujte explicitně, abyste se vyhnuli línému načtení na
každém řádku:

```php
->loadRelations(string|array $relations): static
->getEagerLoadRelations(): array
```

Opakovaná volání sloučí a deduplikují názvy relací. Viz
[Cesty relací](relations.md).

### Vlastní rendering (Blade partialy)

Každý sloupec vlastní svůj **stav/konfiguraci** a deleguje **markup** na Blade
partial pod `packages/table/resources/views/tables/columns/`. Základní textová
buňka se vykresluje přes `text.blade.php`; každý sloupec s custom-UI má svůj
vlastní partial (`badge`, `boolean`, `icon`, `image`, `button`, `toggle`, `poll`,
`split`, `stacked`, `select`, `text-input-*`). Sloupce nikdy nevrací inline HTML
z `renderCell()` — volají `renderView('tables.columns.<name>', [...])`.

Dva způsoby přizpůsobení markupu:

```php
// 1. Přepis u jednotlivých sloupců — nasměrujte jakýkoli sloupec na svůj vlastní Blade pohled.
TextColumn::make('name')->view('columns.my-name-cell');

// 2. Přepis pro celý projekt — publikujte pohledy balíčku a upravte partial.
//    php artisan vendor:publish --tag=wire-table::views
//    pak upravte resources/views/vendor/wire-table/tables/columns/badge.blade.php
```

Pořadí resolvování pohledu: explicitní `->view()` vyhrává, pak pohled balíčku
(`wire-table::tables.columns.<name>`), pak app-level pohled stejného názvu. Váš
partial dostane přesně ta data jako vestavěný — už resolvované primitivy
stavu/konfigurace pro daný sloupec — takže přepisujete jen HTML.

---
order: 20
summary: "Všechny typy entries, které infolist může držet, jaký stav která očekává a jako co ho vykreslí."
---

# Entries

Entry je jeden fakt o záznamu. Po které třídě sáhnete, rozhoduje to, co hodnota
*je* — řetězec, stav s barvou, boolean, seznam, obrázek, diff — a všechny sdílejí
slovník popisku, ikony, barvy, velikosti a viditelnosti z
[Foundation](../foundation/index.md).

## TextEntry

Výchozí entry. Sdílí kanonický concern `FormatsState` se sloupci tabulek, takže `money()`, `numeric()`, `date()`, `dateTime()` a `since()` formátují hodnotu přesně jako odpovídající `TextColumn`.

```php
TextEntry::make('total')->money('Kč');                 // 1 234 Kč
TextEntry::make('weight')->numeric(2);                  // 1 234,50
TextEntry::make('created_at')->date();                  // 20.06.2026
TextEntry::make('updated_at')->dateTime()->since();     // 3 hours ago

TextEntry::make('status')->badge()->color(Color::Success);
TextEntry::make('email')->icon('envelope')->copyable();
TextEntry::make('bio')->limit(120);
TextEntry::make('name')->weight('bold');                // normal|medium|semibold|bold|light
TextEntry::make('notes')->prose();                      // dlouhý text, prose stylování
TextEntry::make('tags')->bulleted();                    // array → odrážkový seznam
TextEntry::make('aliases')->listWithLineBreaks();       // array → seznam oddělený řádky
```

### TextEntry API

| Metoda | Popis |
|--------|-------------|
| `money(?string $currency = 'CZK')` | Formátovat jako měnu |
| `numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')` | Formátovat jako číslo |
| `date(?string $format = 'd.m.Y')` / `dateTime(?string $format = 'd.m.Y H:i')` | Formátovat datum / datetime |
| `since()` | Vykreslit datum jako lidský diff (`diffForHumans`) |
| `badge(bool = true)` | Vykreslit hodnotu jako barevnou pilulku |
| `color(string\|Color\|Closure)` | Barva badge / textu |
| `icon(string\|Closure, ?string $position)` | Přední ikona |
| `copyable(bool = true)` | Přidat prvek copy-to-clipboard |
| `limit(?int)` | Zkrátit dlouhý text |
| `weight(?string)` | Tloušťka písma |
| `prose(bool = true)` | Prose stylování pro dlouhý text |
| `listWithLineBreaks(bool = true)` / `bulleted(bool = true)` | Vykreslit array stav jako seznam |
| `formatStateUsing(Closure)` | Transformovat resolvovanou hodnotu (`$state, $record`) |

## BadgeEntry

Prvotřídní `TextEntry` přednastavený na vykreslení jako badge — ergonomická forma `TextEntry::make(...)->badge()`. Dědí plné `TextEntry` API (barva, ikona, formátování), takže badge chrome zůstane vlastněno na jednom místě.

```php
use NyonCode\WireCore\Infolists\Components\BadgeEntry;

BadgeEntry::make('status')
    ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray)
    ->icon('check-circle');
```

## IconEntry

Vykreslí ikonu odvozenou ze stavu. Použijte `boolean()` pro true/false nebo `icons()` pro mapu hodnota → ikona.

```php
IconEntry::make('is_verified')->boolean();              // ✓ success / ✕ danger

IconEntry::make('is_active')
    ->boolean()
    ->trueIcon('check-badge')->trueColor('success')
    ->falseIcon('no-symbol')->falseColor('gray');

IconEntry::make('status')
    ->icons(['draft' => 'pencil', 'published' => 'check', 'archived' => 'archive-box'])
    ->colors(['draft' => 'gray', 'published' => 'success', 'archived' => 'warning']);
```

### IconEntry API

| Metoda | Popis |
|--------|-------------|
| `boolean(bool = true)` | Mapovat truthy/falsy stav na check/x ikony |
| `trueIcon()` / `falseIcon()` | Přepsat boolean ikony |
| `trueColor()` / `falseColor()` | Přepsat boolean barvy |
| `icons(array\|Closure)` | Mapovat hodnoty stavu na názvy ikon |
| `colors(array\|Closure)` | Mapovat hodnoty stavu na názvy barev |

## BooleanEntry

Prvotřídní `IconEntry` přednastavený na boolean režim — ergonomická forma `IconEntry::make(...)->boolean()`. Truthy stav vykreslí success check ikonu, falsy stav danger x ikonu; ikony a barvy zůstanou přepsatelné.

```php
use NyonCode\WireCore\Infolists\Components\BooleanEntry;

BooleanEntry::make('is_verified');

BooleanEntry::make('is_active')
    ->trueIcon('check-badge')->trueColor('success')
    ->falseIcon('no-symbol')->falseColor('gray');
```

## ListEntry

Vykreslí kolekční stav jako odrážkový seznam nebo řadu badge chipů — střední cesta mezi jednou `TextEntry` a plnou `RepeatableEntry`. Stav může být array/iterable nebo oddělovaný řetězec rozdělený `separator()`. Položky znovupoužívají `TextEntry` formátování (číslo/měna/datum, `formatStateUsing()`, `limit()`).

```php
use NyonCode\WireCore\Infolists\Components\ListEntry;

ListEntry::make('tags');                                  // odrážkový seznam

ListEntry::make('tags')->badge()->color('primary');       // badge chipy

ListEntry::make('roles')->separator(',');                 // "admin, editor" → dvě položky

ListEntry::make('categories')->badge()->limitList(3);     // první 3 chipy + pilulka "+N"
```

### ListEntry API

| Metoda | Popis |
|--------|-------------|
| `badge(bool = true)` | Vykreslit položky jako badge chipy místo odrážkového seznamu |
| `bulleted(bool = true)` | Přepnout odrážky seznamu (non-badge režim) |
| `separator(?string)` | Rozdělit skalární řetězcový stav na položky |
| `limitList(?int)` | Omezit viditelné položky; zbytek se sbalí do `+N` indikátoru |
| `color(string\|Color\|Closure)` | Barva chipu / textu |
| `icon(string\|Closure)` | Přední ikona na každém chipu |

## ImageEntry

Vykreslí stav jako jeden nebo více obrázků. Absolutní/data URL se použijí doslovně; relativní cesty resolvují přes nakonfigurovaný `disk()`.

```php
ImageEntry::make('avatar')->circular()->imageSize(56);

ImageEntry::make('logo')->disk('public')->defaultImageUrl('/img/placeholder.png');

ImageEntry::make('gallery')->stacked()->imageSize(40);  // array stav → překrytá galerie
```

### ImageEntry API

| Metoda | Popis |
|--------|-------------|
| `disk(?string)` | Storage disk pro relativní cesty |
| `imageSize(int)` | Šířka/výška v pixelech |
| `circular(bool = true)` | Zakulatit obrázek |
| `stacked(bool = true)` | Překrýt více obrázků |
| `defaultImageUrl(?string)` | Fallback, když je stav prázdný |

## ColorEntry

Vykreslí vzorek plus hodnotu barvy, volitelně kopírovatelnou.

```php
ColorEntry::make('brand_color')->copyable();
```

## KeyValueEntry

Vykreslí array (nebo JSON-cast atribut) jako tabulku klíč/hodnota.

```php
KeyValueEntry::make('meta')
    ->keyLabel('Attribute')
    ->valueLabel('Value');
```

## HtmlEntry

Uložený obsah z editoru vytištěný jako **markup**, ne jako escapovaný text, se
všemi zmínkami v něm dohledanými při vykreslení.

```php
use NyonCode\WireCore\Infolists\Components\HtmlEntry;

HtmlEntry::make('body')
    ->zone('business');     // kam vedou zmínky, když resource routuje po zónách
```

Je to záměrně samostatná entry, ne přepínač na [TextEntry](#textentry): textová
entry escapuje a escapovat musí dál. Vytisknout markup je rozhodnutí, které
aplikace dělá vědomě a o sloupci, který má pod kontrolou — takže je to jiné jméno,
ne přepínač, který potichu vypne escapování.

**Zmínky se čtou znovu, nepřehrávají se.** `@člověk` nebo `#článek` uložený v
dokumentu nese identitu, ne jméno a odkaz, takže je entry při každém vykreslení
dohledá znovu: přejmenovaný záznam se tu čte přejmenovaně a zmínka, jejíž záznam
je pryč (nebo na něj čtenář nemá právo), degraduje na prostý text místo mrtvého
odkazu. `zone()` je to, co předá stránka, která si v `mount()` přečetla
`Zone::current()`, aby odkazy mířily do místa, kde čtenář právě je; bez ní vedou
přes výchozí zónu. Zapisovací polovinu popisuje
[TiptapEditor → Zmínky](../../forms/fields/tiptap-editor.md).

```php
->zone(?string $zone)             // zóna, ve které se odkazy zmínek dohledávají
->getRenderedHtml(): string       // dokument se všemi dohledanými zmínkami
```

## RepeatableEntry

Vykreslí vnořené schéma entries jednou pro každou položku iterovatelného stavu — `hasMany` relace nebo pole řádků.

```php
RepeatableEntry::make('items')
    ->columns(3)
    ->schema([
        TextEntry::make('label')->weight('medium'),
        TextEntry::make('price')->money('Kč'),
        TextEntry::make('qty')->numeric(),
    ]);
```

| Metoda | Popis |
|--------|-------------|
| `schema(array)` | Schéma entries vykreslené pro každou položku |
| `columns(int)` | Sloupce gridu na řádek |
| `contained(bool = true)` | Obalit každý řádek do ohraničené karty |
| `actions(array)` | Akční tlačítka pro každý řádek (viz [Akce](actions.md#akce)) |
| `with(array\|string)` | Eager load relací na řádcích (viz níže) |

### Předcházení N+1 na řádcích relace

Když jsou řádky Eloquent modely, jejichž dětské entries čtou **vnořenou** cestu relace (např. `product.name` na každém řádku objednávky), čtení té cesty načte relaci líně, jednou pro každý řádek — N+1. Deklarujte relace pomocí `with()` a načtou se eager loadem přes všechny řádky v jednom dotazu před renderem:

```php
RepeatableEntry::make('lines')
    ->with(['product', 'tax'])              // jeden dotaz na relaci, ne na řádek
    ->schema([
        TextEntry::make('product.name'),
        TextEntry::make('tax.rate')->numeric(2),
    ]);
```

`with()` je no-op pro array řádky a slučuje se napříč opakovanými voláními. (Relace, která pohání samotné repeatable — `lines` — by měla být načtená eager loadem na rodičovském dotazu jako obvykle.)

<a id="actions"></a>

## ChangesEntry

Vykreslí rozdíl před/po jako **jednu** tabulku — řádek na každé pole, které se pohnulo, stará a nová hodnota vedle sebe a barevně, s jednou sadou záhlaví pro celý rozdíl.

```php
ChangesEntry::make('changes');
```

Sáhněte po něm všude, kde se zaznamenává, co se změnilo: záznam auditu, revize, výsledek synchronizace. Právě tohle kreslí stránka záznamu v modulu auditu a totéž kreslí slide-over s auditní stopou nad záznamem — stejná tabulka, takže rozdíl se čte v obou místech stejně.

### Co přijímá jako stav

Dva tvary, a rozliší si je sám, protože volající má jeden z nich a oba znamenají totéž:

- mapu `{old, new}`, kterou produkuje záznam auditu — `['status' => ['old' => 'draft', 'new' => 'sent']]`
- řádky, které už jsou `{field, before, after}`

Tak či tak hodnoty projdou přes `NyonCode\WireCore\Foundation\ValueObjects\ChangeSet`, který vlastní **jak se uložená hodnota čte**: `null` zůstane null, aby renderer mohl říct *(prázdné)*, boolean se píše `true` / `false` místo `1` a ničeho, a pole je JSON s ponechanými lomítky i diakritikou.

Ten jediný vlastník je pointa. To pravidlo bývalo napsané dvakrát — jednou v modulu auditu a jednou v Blade ternáru ve stopě — a ta kopie, na kterou nedosáhl žádný test, tiskla booleany jako `1`.

### V kontextu

```php
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\ChangesEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

public function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Section::make('what-happened')->label('Co se stalo')->columns(2)->schema([
            TextEntry::make('event')->badge(),
            TextEntry::make('created_at'),
        ]),

        Section::make('changes')->label('Změny')->schema([
            ChangesEntry::make('changes')                                  // [tl! focus:start]
                // Stavem je rozdíl, nikdy záznam: předejte dvojice,
                // nebo řádky, které jste si už postavili.
                ->state(fn ($record): array => $record->getChangeDiff())
                ->hiddenLabel()
                ->placeholder('Žádné pole se nezměnilo.'),                 // [tl! focus:end]
        ]),
    ]);
}
```

`dense()` ho nakreslí o velikost menší — pro rozdíl, který je uvnitř něčeho jiného doprovodným důkazem, ne předmětem stránky. Přesně tak ho kreslí slide-over s auditní stopou.

### ChangesEntry API

```php
->dense(bool $dense = true)           // menší písmo, pro rozdíl uvnitř slide-overu nebo timeline
->isDense(): bool
->getRows(): array                    // [['field' => string, 'before' => ?string, 'after' => ?string], ...]
```

Všechno ostatní — `label()`, `hiddenLabel()`, `state()`, `placeholder()`, `columnSpan()`, `visible()` — je sdílená plocha entry popsaná výše.

## Související

- [Infolisty](index.md) — objekt, do kterého se tyhle entries skládají
- [Akce](actions.md) — jak vedle nich postavit tlačítko
- [Sloupce](../../table/columns/index.md) — tabulkové protějšky sdílející tytéž povrchy
- [Editovatelné panely](../record-panels.md) — entries, které zapisují zpět

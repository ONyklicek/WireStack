---
order: 23
nav: false
summary: Vykreslí buňku jako barevnou pilulku, jejíž barva a ikona se odvozují ze stavu záznamu.
---

# BadgeColumn

Vykreslí buňku jako barevnou pilulku. Sáhněte po něm, když je hodnota *stav* —
status, role, priorita — a barva je to, co čtenář skutečně skenuje očima. Pro
běžnou hodnotu, která jen potřebuje akcentovou barvu, je lehčím nástrojem
`TextColumn` s `->color()`.

```php
use NyonCode\WireTable\Columns\BadgeColumn;
```

## Jak to funguje

Badge se u každé buňky ptá na dvě věci — *jakou barvu* a *jakou ikonu* — a na
každou odpovídá průchodem žebříčku, dokud některá příčka neodpoví. **Barva**, v
tomto pořadí:

1. **`->colorUsing()`** — closura běží první a vyhrává, pokud vrátí cokoli jiného
   než `null`. Vrácením `null` u některých stavů je předáte příčkám níž.
2. **`->colors()`** — mapa, dohledaná podle stavu jako klíče. Enum stav se
   nejdřív rozbalí na svou backing hodnotu, takže `Status::Active` najde klíč
   `'active'`.
3. **Vlastní barva stavu** — enum implementující kontrakt `HasColor` si barvu
   pojmenuje sám a žádná mapa není potřeba.
4. **`->color()`** — statická barva sloupce, aby její nastavení nebylo na
   stavovém sloupci tiše ignorováno.
5. **`gray`** — neutrální podlaha. Badge se vždycky vykreslí s *nějakou* barvou.

**Ikony** procházejí stejný žebříček — `->iconUsing()`, `->icons()`, kontrakt
`HasIcon` na enumu, pak `->icon()` sloupce — s jedním rozdílem: nemají podlahu.
Když nesedí nic, ikona není a badge se vykreslí jako holá pilulka.

Čtyři věci, které je dobré vědět, než začnete psát řetěz:

- **Popisek se resolvuje odděleně od barvy.** Text pilulky pochází z běžné
  formátovací pipeline sloupce (`->formatStateUsing()`, casty, labely enumů),
  nikdy z barevné mapy. Enum stav bez kontraktu `HasLabel` se čte jako headline
  názvu case — `InReview` → „In Review“.
- **Do `->colors()` předávejte pole, ne closuru.** Signatura přijímá
  `array|Closure`, protože je sdílená s povrchy, které znají záznam (infolist
  entries closuru vyhodnotí nad svým záznamem). Sloupec se konfiguruje dřív, než
  jakýkoli záznam existuje, takže closurová mapa tady nenajde nic a všechny
  badge spadnou na podlahovou barvu. Pro dynamické barvy použijte
  `->colorUsing()`.
- **Cena je za odlišný stav, ne za řádek.** Stavy mají z podstaty nízkou
  kardinalitu, takže se vykreslené markup memoizuje podle resolvovaných dat:
  tisíc řádků se čtyřmi statusy vykreslí čtyři badge. Closury výš proto běží za
  hodnotu stavu, ne za záznam — nedávejte do nich logiku závislou na záznamu.
- **Hodnota se escapuje.** Jako u každé textové buňky je popisek escapovaný,
  dokud se sloupec nepřihlásí k `->html()` — hodnota záznamu jako
  `<img onerror=…>` je text, ne markup. Stav `null` nebo prázdný vykreslí text
  prázdné buňky, ne prázdnou pilulku.

## Základní použití

Mapa je klíčovaná **stavem**, hodnota je barva, kterou pro něj badge dostane:

```php
BadgeColumn::make('status')
    ->colors([
        'active' => 'success',      // zelený badge pro 'active'
        'banned' => 'danger',       // červený badge pro 'banned'
        'pending' => 'warning',     // žlutý badge pro 'pending'
        'draft' => 'gray',          // šedý badge pro 'draft'
        'featured' => 'primary',    // modrý badge pro 'featured'
        'processing' => 'info',     // azurový badge pro 'processing'
    ])
```

Stav, který mapa neuvádí, propadne žebříčkem výš. Hodnoty jde zapsat i enumem
`Color` (`'active' => Color::Success`) a k dispozici je celá paleta Tailwindu —
viz [Theming](../../theming.md).

## S ikonami

`->icons()` je klíčovaná stavem úplně stejně a stav, který mapa neuvádí, spadne
zpět na vlastní `->icon()` sloupce — když ani ten není nastavený, ikona se
nezobrazí. Enum stav implementující kontrakt `HasIcon` si ikonu vybere sám i bez
mapy.

```php
BadgeColumn::make('priority')
    ->colors([
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'gray',
    ])
    ->icons([
        'critical' => 'exclamation',
        'high' => 'arrow-up',
        'medium' => 'minus',
        'low' => 'arrow-down',
    ])
```

## Dynamické barvy

Když je barva funkcí hodnoty a ne pevného slovníku — skóre, částka, stáří —
odvoďte ji. Closura dostane stav a běží před mapou:

```php
// Resolvování barvy pomocí closury
BadgeColumn::make('score')
    ->colorUsing(fn (int $state) => match(true) {
        $state >= 90 => 'success',
        $state >= 70 => 'info',
        $state >= 50 => 'warning',
        default => 'danger',
    })
    ->iconUsing(fn (int $state) => $state >= 90 ? 'star' : null)
```

## Enum stavy

Enum, který implementuje kontrakty `HasLabel` / `HasColor` / `HasIcon`, si nese
vlastní prezentaci a sloupec nepotřebuje žádné mapy. Tentýž enum se pak čte
stejně na buňce tabulky, v infolist entry i jako `<select>` option:

```php
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasIcon;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    public function getLabel(): string // [tl! focus:start]
    {
        return match ($this) {
            self::Pending => 'Čeká na platbu',
            self::Shipped => 'Na cestě',
            self::Cancelled => 'Zrušeno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Shipped => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return $this === self::Cancelled ? 'x-circle' : null;
    } // [tl! focus:end]
}

// S atributem castnutým na enum je sloupec jen ten atribut.
BadgeColumn::make('status')
```

Mapa pořád přebíjí vlastní barvu enumu — právě tak může jedna tabulka
prezentovat sdílený enum jinak, aniž by se enum měnil.

## Vlastní popisek + badge

`->formatStateUsing()` přepíše text pilulky, aniž by sáhl na barevný žebříček —
mapa zůstává klíčovaná syrovým stavem:

```php
BadgeColumn::make('role')
    ->formatStateUsing(fn (string $state) => match($state) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'editor' => 'Editor',
        default => ucfirst($state),
    })
    ->colors([
        'super_admin' => 'danger',
        'admin' => 'primary',
        'editor' => 'success',
    ])
```

## Velikost

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg — výchozí md
```

`->xl()` na sdíleném size API existuje, ale badge povrch ho vykreslí s paddingem
`md`; největší pilulka je `lg`.

## Rozšířený příklad

Moderační tabulka, kde jeden sloupec nese tři signály najednou: barva pochází z
mapy, ikona označí jen stavy, které vyžadují pozornost, a popisek je přepsaný pro
čtenáře, kteří nemyslí ve slugách.

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;

class ArticleTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Article::class)
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold'),

                BadgeColumn::make('status') // [tl! focus:start]
                    ->colors([
                        'published' => 'success',
                        'in_review' => 'warning',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                    ])
                    ->icons([
                        'in_review' => 'clock',     // ikonu dostanou jen stavy,
                        'rejected' => 'x-circle',   // které chtějí druhý pohled
                    ])
                    ->formatStateUsing(fn (string $state) => str($state)->headline())
                    ->size('sm'), // [tl! focus:end]

                TextColumn::make('published_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->paginated();
    }

    public function render()
    {
        return view('livewire.article-table');
    }
}
```

## API BadgeColumn

Samotný badge povrch. Všechno ostatní, co sloupec umí — `->label()`,
`->sortable()`, `->visible()`, formátování, editace — je sdílené API sloupců,
popsané v [Sloupce](index.md).

```php
->colors(array $map)                  // ['state' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)             // fn ($state) => 'color_name'|Color|null — přebíjí mapu
->icons(array $map)                   // ['state' => 'icon_name'|Icon, ...]
->iconUsing(Closure $fn)              // fn ($state) => 'icon_name'|Icon|null — přebíjí mapu
->color(string|Color $color)          // záložní barva, když stav nemapuje na nic
->icon(string|Icon $icon)             // záložní ikona, když stav nemapuje na nic
->size(string|Size $size)             // 'xs'|'sm'|'md'|'lg' — výchozí 'md'
->xs() / ->sm() / ->md() / ->lg()     // presety velikosti
->getSize(): string
->getColorForState($state): ?string   // resolvovaná barva včetně celého žebříčku
->getIconForState($state): ?string    // resolvovaná ikona včetně celého žebříčku
```

## Související

- [Sloupce](index.md) — sdílené API sloupců, které dědí každý sloupec
- [IconColumn](icon.md) — tentýž stavový žebříček vykreslený jako samotná ikona
- [PollColumn](poll.md) — badge nad živě pollovanou hodnotou
- [Theming](../../theming.md) — barevný slovník, ze kterého tyto mapy čerpají

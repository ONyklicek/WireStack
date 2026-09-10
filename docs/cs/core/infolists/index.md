---
order: 10
summary: "Read-only protějšek formuláře: co infolist je, jak se skládá schéma, odkud se bere hodnota každé entry a kompletní API objektu."
---

# Infolisty

Infolist jeden záznam **zobrazuje** tak, jak ho formulář edituje: stejné
deklarativní schéma, stejné sekce a mřížky, stejné concerny pro popisek, ikonu,
barvu a viditelnost. Co nemá, je stav, validace a odeslání — read-only není
formulář se zakázanými vstupy, je to jiný objekt s menší prací.

```php
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Components\IconEntry;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Colors\Color;

Infolist::make()
    ->record($user)
    ->schema([
        Section::make('Profile')->icon('user')->columns(2)->schema([   // [tl! focus:start]
            TextEntry::make('name')->weight('bold'),
            TextEntry::make('email')->icon('envelope')->copyable(),
            TextEntry::make('created_at')->dateTime()->since(),
            TextEntry::make('status')
                ->badge()
                ->color(fn ($state) => $state === 'active' ? Color::Success : Color::Gray),
            IconEntry::make('is_verified')->boolean(),
        ]),                                                              // [tl! focus:end]
    ]);
```

> **Nováček?** Infolist je jen seznam věcí, které chcete o jednom záznamu ukázat. Postavíte ho v PHP, předáte mu záznam a vyechujete v Blade. Zbytek této stránky staví od nejjednoduššího možného příkladu.

## Instalace

Infolisty se dodávají s `wire-core` — nic navíc k instalaci. Ujistěte se, že pohledy balíčku jsou ve vašich Tailwind content cestách, aby se styly vygenerovaly:

```js
export default {
    content: [
        // ...vaše cesty aplikace
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
    ],
}
```

## Rychlý start

Infolist žije na Livewire komponentě. Nejjednodušší způsob je [computed property](https://livewire.laravel.com/docs/computed-properties), který vrací `Infolist`, jenž pak vyechujete v pohledu komponenty.

```php
use Livewire\Attributes\Computed;
use Livewire\Component;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Foundation\Schema\Section;

class ShowUser extends Component
{
    public User $user;          // záznam, který chcete zobrazit

    #[Computed]
    public function infolist(): Infolist
    {
        return Infolist::make() // [tl! focus:start]
            ->record($this->user)               // 1. dát mu záznam
            ->schema([                           // 2. vypsat, co ukázat
                Section::make('Profile')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),         // čte $user->name
                        TextEntry::make('email')->copyable(),
                    ]),
            ]); // [tl! focus:end]
    }

    public function render()
    {
        return view('livewire.show-user');
    }
}
```

```blade
{{-- resources/views/livewire/show-user.blade.php --}}
<div>
    {{ $this->infolist }}        {{-- 3. vykreslit --}}
</div>
```

To je celá smyčka: **záznam dovnitř → schéma → vyechovat ven.** `{{ $this->infolist }}` funguje, protože `Infolist` je `Htmlable` a `$this->infolist` resolvuje computed property — žádný speciální helper ani trait.

> Metodu můžete pojmenovat jakkoli (`$this->orderInfolist`, `$this->summary`, …) a mít jich na jedné komponentě několik — viz [Skládání schématu](#skladani-schematu).

## Typy entries přehledně

| Entry | Třída | Použití pro |
|-------|-------|---------|
| **Text** | `TextEntry` | Text, čísla, měna, data — plus badge, kopírování, list a zkrácení |
| **Badge** | `BadgeEntry` | `TextEntry` přednastavený jako barevná pilulka |
| **Icon** | `IconEntry` | Booleany a stav → mapy ikon |
| **Boolean** | `BooleanEntry` | `IconEntry` přednastavený na boolean check/x |
| **List** | `ListEntry` | Kolekce jako odrážkový seznam nebo badge chipy |
| **Image** | `ImageEntry` | Avatary a náhledy (jeden nebo galerie) |
| **Color** | `ColorEntry` | Barevný vzorek + jeho hodnota |
| **Key-value** | `KeyValueEntry` | Array / JSON atribut jako tabulka klíč/hodnota |
| **Repeatable** | `RepeatableEntry` | Vnořené schéma entries opakované pro každou položku relace/pole |
| **Changes** | `ChangesEntry` | Rozdíl před/po jako jedna tabulka, řádek na pole |
| **Html** | `HtmlEntry` | Uložený bohatý text vypsaný jako markup, se [zmínkami](../../forms/fields/tiptap-editor.md#zminky) rozbalenými při každém renderu |

> **Enum casty.** Entries čtou enum-cast atributy bezpečně: `TextEntry` vykreslí popisek enumu
> (přes kontrakt `Enum\HasLabel`, jinak backing hodnota / název case) a `IconEntry`
> automaticky resolvuje svou ikonu a barvu z enumu implementujícího `Enum\HasColor` / `Enum\HasIcon`.
> Viz [Foundation → Enumy](../foundation/enums.md#enumy).

## Objekt Infolist

`Infolist::make()` postaví kontejner; `record()` naváže zdroj dat (Eloquent model **nebo** prosté pole), `schema()` drží entries a layout a `columns()` nastaví top-level grid.

```php
Infolist::make()
    ->record($order)        // Model|array
    ->columns(2)            // top-level sloupce gridu (výchozí 1)
    ->schema([ /* … */ ]);
```

`state(array $data)` je alias pro `record()`, když je zdroj prosté pole:

```php
Infolist::make()->state(['name' => 'Ada', 'email' => 'ada@example.com'])->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
]);
```

Záznam se automaticky propaguje do každé entry, když se infolist vykreslí, rekurzivně skrz layoutové komponenty.

`getSchema()` je místo, kde se schéma stává konečným, a čte se přes plugin hook: `infolist.configuring` tam běží jednou a odpověď se memoizuje — takže nainstalovaný balíček může přidat entry do detailu, který nevlastní, a dvakrát přečtené schéma tu entry nedostane dvakrát. Nové volání `schema()` se zeptá znovu, protože znovu deklarované schéma je jiný infolist. Viz [Hooky](../plugins/hooks.md).

<a id="composing-the-schema"></a>

## Skládání schématu

> **Můžu přidat víc než pár polí?** Ano — `schema()` je jen seznam. Dejte tam kolik entries chcete, seskupte je kolika sekcemi chcete a míchejte libovolné typy entries dohromady. Není žádný limit a žádné speciální zapojení; jen uspořádáváte objekty v poli.

**Přidejte kolik entries potřebujete.** Každý řádek `make('column')` ukáže jednu hodnotu:

```php
Section::make('Profile')->columns(2)->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
    TextEntry::make('phone'),
    TextEntry::make('created_at')->date(),
    IconEntry::make('is_verified')->boolean(),
    // ...přidejte další, v libovolném pořadí
]);
```

**Použijte několik sekcí** k rozdělení záznamu do logických skupin — každá je samostatná karta:

```php
Infolist::make()->record($order)->schema([
    Section::make('Customer')->columns(2)->schema([
        TextEntry::make('customer.name'),
        TextEntry::make('customer.email'),
    ]),
    Section::make('Payment')->columns(2)->schema([
        TextEntry::make('total')->money(),
        TextEntry::make('status')->badge(),
    ]),
    Section::make('Notes')->schema([
        TextEntry::make('notes')->prose(),
    ]),
]);
```

**Vnořte layouty** — `Grid` nebo `Fieldset` může žít uvnitř `Section` a `RepeatableEntry` nese své vlastní sub-schéma:

```php
Section::make('Order')->schema([
    Grid::make()->columns(3)->schema([
        TextEntry::make('number'),
        TextEntry::make('placed_at')->date(),
        TextEntry::make('total')->money(),
    ]),
    RepeatableEntry::make('items')->columns(3)->schema([
        TextEntry::make('label'),
        TextEntry::make('qty')->numeric(),
        TextEntry::make('price')->money(),
    ]),
]);
```

**Sekci ani nepotřebujete** — entries mohou sedět přímo v infolistu, uspořádané top-level `columns()`:

```php
Infolist::make()->record($user)->columns(2)->schema([
    TextEntry::make('name'),
    TextEntry::make('email'),
]);
```

**Několik infolistů na jedné stránce** — jen definujte více než jeden computed property a vyechujte každý, kam chcete:

```php
#[Computed]
public function profile(): Infolist { /* ... */ }

#[Computed]
public function billing(): Infolist { /* ... */ }
```

```blade
<div class="space-y-6">
    {{ $this->profile }}
    {{ $this->billing }}
</div>
```

> **Pravidlo palce:** pokud dokážete popsat, co ukázat, jako „tato hodnota, pak ta hodnota, seskupené pod těmito nadpisy“, můžete to vyjádřit zde — jedna entry na hodnotu, jedna sekce na skupinu.

<a id="state-resolution"></a>

## Resolvování stavu

Ve výchozím stavu entry čte svou hodnotu ze záznamu podle **názvu**, s tečkovou notací pro relace a vnořená pole (resolvováno přes `data_get`):

```php
TextEntry::make('name');              // $record->name
TextEntry::make('company.name');      // $record->company->name
TextEntry::make('address.city');      // $record['address']['city']
```

Přepište resolvování pomocí `state()` (dostane záznam), transformujte resolvovanou hodnotu pomocí `formatStateUsing()` (dostane `$state, $record`) a poskytněte fallback pomocí `default()`:

```php
TextEntry::make('full_name')
    ->state(fn ($record) => $record->first_name.' '.$record->last_name);

TextEntry::make('status')
    ->formatStateUsing(fn ($state) => ucfirst($state))
    ->default('—');
```

`color()` a jakákoli jiná dynamická vlastnost také přijímají closuru resolvovanou s `$state` a `$record`:

```php
TextEntry::make('priority')
    ->badge()
    ->color(fn ($state) => match ($state) {
        'high' => Color::Danger,
        'medium' => Color::Warning,
        default => Color::Gray,
    });
```

<a id="layout"></a>

## Layout

Infolisty používají kanonický schema layout z `NyonCode\WireCore\Foundation\Schema` — stejné třídy, které form layouty subclassují:

```php
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Fieldset;

Section::make('Billing')
    ->icon('credit-card')
    ->description('Plan and invoicing details')
    ->columns(2)
    ->collapsible()
    ->schema([
        Grid::make()->columns(3)->schema([ /* entries */ ]),
        Fieldset::make('Tax')->schema([ /* entries */ ]),
    ]);
```

Každá entry přijímá `columnSpan(int)` / `columnSpanFull()` pro rozpětí gridu.

### Flex

`Flex` uspořádá své potomky vedle sebe na jedné vodorovné (flexbox) ose, na malých obrazovkách je skládá svisle — užitečné pro spárování detailní karty se souhrnem nebo avataru s biem. Potomci rostou a sdílejí řádek rovnoměrně; `from()` nastaví breakpoint (`sm` / `md` / `lg`, výchozí `md`), na kterém se řádek stane vodorovným.

```php
use NyonCode\WireCore\Foundation\Schema\Flex;

Flex::make()->from('lg')->schema([
    Section::make('Details')->schema([ /* entries */ ]),
    Section::make('Summary')->schema([ /* entries */ ]),
]);
```

## Infolist API

| Metoda | Popis |
|--------|-------------|
| `make()` | Vytvořit infolist |
| `record(Model\|array)` | Navázat zdroj dat |
| `state(array)` | Navázat prosté pole (alias `record()`) |
| `schema(array)` | Entries a layoutové komponenty |
| `columns(int)` | Top-level sloupce gridu (výchozí 1) |
| `getRecord()` / `getSchema()` / `getColumns()` / `getLivewireComponent()` | Accessory |
| `toHtml()` | Vykreslit (také přes `Htmlable` echo) |

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Entries](entries.md) | Všechny typy entries — text, badge, ikona, boolean, seznam, obrázek, barva, key-value, opakovatelná, změny |
| [Akce](actions.md) | Tlačítka na infolistu a infolist vykreslený uvnitř modalu akce |

## Související

- [Editovatelné panely](../record-panels.md) — stejný tvar, ale s entries, které zapisují zpět
- [Schéma](../schema/overview.md) — layoutový slovník sdílený s formuláři
- [Panely: Stránky](../../panels/pages.md) — stránka detailu, která tohle vykresluje
- [Formuláře](../../forms/overview.md) — editační protějšek

---
order: 10
summary: Jedna vodorovná osa pro řádek prvků, které si dělí prostor a na malých obrazovkách se skládají pod sebe.
---

# Flex

Jeden řádek, jehož děti si mezi sebou dělí prostor. Sáhni po `Flexu`, když je
[`Grid`](grid.md) příliš tuhý — vyhledávací pole, které má zabrat, co zbude vedle
tlačítka pevné šířky, toolbar, dvojice panelů s nestejnou váhou. Grid dá každému
dítěti stejný díl; flexový řádek je nechá dohodnout se.

> Neplést s tabulkovým
> [`SplitColumn`](../../../table/columns/split.md), který dělí prostor *uvnitř
> jedné buňky tabulky*.

```php
use NyonCode\WireCore\Foundation\Schema\Flex;
```

## Jak to funguje

Flexový řádek je **layoutová komponenta**: žádná hodnota, žádná state path, dá se
přidat i odebrat bez dotyku na data.

**Je to nejdřív sloupec a až potom řádek.** Vykreslený element je vždycky
`flex flex-col` a vodorovným ho dělá až breakpoint z `from()` — ve výchozím stavu
`md:flex-row`. Na telefonu se tedy děti skládají pod sebe, což je skoro vždycky
to, co chcete, a není to nic, o co byste museli žádat.

**Každé dítě je zabalené** do boxu s `min-w-0`, a dokud je zapnutý `grow()`
(výchozí stav), i do `flex-1`. Dva důsledky, které stojí za to znát:

- `min-w-0` je důvod, proč dlouhý nezalomitelný řetězec uvnitř dítěte nerozerve
  řádek do šířky. Flexové položky se jinak odmítají zmenšit pod obsah; tohle je ta
  oprava, udělaná za vás.
- `flex-1` je důvod, proč děti vyjdou **stejně velké bez ohledu na obsah**. Vypněte
  ho přes `grow(false)`, když chcete přirozené šířky — tlačítko velké jako tlačítko
  vedle vstupu, který si vezme zbytek.

**Tři setry mají uzavřený slovník a cokoli mimo něj se tiše ignoruje:**

- `from()` rozumí `sm`, `md` a `lg`. **Cokoli jiného spadne na `md`** — `from('xl')`
  neselže, jen se chová, jako byste ho nenapsali.
- `justify()` rozumí `start`, `end`, `center`, `between`, `around`, `evenly`;
  neznámá hodnota nevygeneruje žádnou třídu.
- `align()` rozumí `start`, `end`, `center`, `stretch`, `baseline`, stejným
  způsobem.

`gap()` je krok na Tailwindí škále 0–12, výchozí `4`, a ořízne se do toho rozsahu
místo aby se odmítl.

Děti se před vykreslením filtrují vlastní podmínkou `visible()`, takže skryté dítě
vrátí svůj prostor ostatním místo aby po něm zůstala díra.

## Základní použití

```php
Flex::make()->schema([
    TextInput::make('first_name'),
    TextInput::make('last_name'),
])
```

Dvě stejné poloviny od `md` nahoru, pod ním pod sebou. To je celý výchozí stav.

## Přirozené šířky místo stejných

```php
Flex::make()
    ->grow(false)          // [tl! focus]
    ->align('end')
    ->schema([
        TextInput::make('search'),
        Button::make('go')->label('Hledat'),
    ])
```

S `grow(false)` si každé dítě vezme šířku, kterou opravdu potřebuje. `align('end')`
zarovná tlačítko na spodní hranu vstupu místo na horní, což chcete vždycky, když
jedno dítě má popisek a druhé ne.

## Řízení řádku

```php
Flex::make()
    ->from('lg')          // vodorovně od lg místo od md
    ->justify('between')  // odtlačit děti od sebe podél řádku
    ->align('center')     // vycentrovat je napříč řádkem
    ->gap(6)              // širší mezera (Tailwindí škála 0–12)
    ->wrap()              // nechat děti spadnout na druhý řádek
    ->schema([...])
```

`wrap()` začne být důležitý, jakmile je zapnuté `grow(false)`: bez růstu si děti
drží přirozené šířky a na úzké obrazovce by se jinak zmáčkly místo zalomily.

## Rozšířený příklad

Filtrační lišta nad tabulkou ve skutečném Livewire hostu. Vstupy si dělí prostor,
tlačítko si drží vlastní šířku:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Flex;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class OrderFilters extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Flex::make()                                   // [tl! focus:start]
                    ->from('md')
                    ->align('end')
                    ->gap(3)
                    ->schema([
                        TextInput::make('search')->label('Hledat objednávky'),
                        Select::make('status')->options([
                            'open' => 'Otevřené',
                            'shipped' => 'Odeslané',
                        ]),
                        DateTimePicker::make('placed_after')->asDate()->label('Vytvořeno po'),
                    ]),                                         // [tl! focus:end]
            ]);
    }
}
```

Tři prvky stejné šířky od `md` nahoru, na telefonu pod sebou, se spodními hranami
zarovnanými, protože jeden z nich nese delší popisek než ostatní.

## Flex API

```php
->from(string $breakpoint)     // 'sm'|'md'|'lg' — výchozí 'md'; cokoli jiného spadne na 'md'
->justify(string $justify)     // 'start'|'end'|'center'|'between'|'around'|'evenly' — výchozí nenastaveno
->align(string $align)         // 'start'|'end'|'center'|'stretch'|'baseline' — výchozí nenastaveno
->gap(int $gap)                // Tailwindí krok 0–12 — výchozí 4
->wrap(bool $condition = true)  // povolit druhý řádek — výchozí false
->grow(bool $condition = true)  // děti vyplní řádek rovnoměrně — výchozí true
->getFrom(): string
->isWrap(): bool
->isGrow(): bool
```

Všechno ostatní — `label()`, `schema()`, `visible()`, `columnSpan()` — je společný
layoutový povrch. Viz
[Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Grid](grid.md) — stejné sloupce, když jsou děti opravdu rovnocenné
- [Section](section.md) — nadpis a skládání kolem skupiny
- [Fieldset](fieldset.md) — legenda, když seskupení nese význam

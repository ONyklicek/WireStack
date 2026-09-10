---
order: 10
summary: Vícesloupcový grid pro rozmístění komponent, po breakpointech — týž layout ve formuláři i v infolistu.
---

# Grid

Nejjednodušší způsob, jak dát dvě věci vedle sebe. `Grid` nenese vlastní hodnotu:
vezme počet sloupců, rozloží do nich děti a jde stranou. Sáhni po něm, když je
formulář stěna vstupů přes celou šířku a jméno / příjmení očividně patří na jeden
řádek.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
```

## Jak to funguje

Grid je **layoutová komponenta**: nenese stav ani hodnotu, takže jeho přidání,
odebrání nebo přeskládání se nikdy nedotkne dat. Do stavu mapují jen pole. Proto
se týž `Grid` vykreslí ve formuláři, v infolistu i v modalu akce — třída vlastní
konfiguraci a Blade view každého balíčku vlastní vzhled.

**Počet sloupců se vyhodnotí v jednom ze dvou tvarů**, obojí přes
`ResponsiveGrid::cols()`:

- **Int** dá mobile-first přeskládání: `columns(3)` je jeden sloupec na telefonu
  a tři od breakpointu `md` nahoru. Nikdy to nejsou tři sloupce na telefonu, což
  je odpověď, kterou většina lidí chce a nikdo nepíše.
- **Mapa po breakpointech** to řekne přesně: `['default' => 1, 'md' => 2, 'xl' => 3]`.
  Klíče jsou `default` (nebo `''`, nebo `0`) a pak `sm`, `md`, `lg`, `xl`, `2xl`.
  Počty se ořežou na 1–12 a **neznámý klíč breakpointu se tiše ignoruje** —
  `'medium' => 2` nevyrobí vůbec nic.

Výchozí hodnota jsou **2 sloupce**. Mezera mezi dětmi je pevně Tailwindí `gap-4`;
grid, který potřebuje jiný rytmus, je [`Flex`](flex.md), ten bere `->gap()`.

Děti se vykreslují v pořadí deklarace a dítě, jehož podmínka `visible()` /
`hidden()` je nepravdivá, **z toku úplně vypadne** místo aby se vykreslilo prázdné
— grid se za ním zavře.

**Past: `columnSpan()` rozumí jen 2, 3, 4 a `full`.** Mapa rozpětí je uzavřený
`match`, takže `->columnSpan(5)` propadne na výchozí hodnotu a pole tiše zabere
jeden sloupec. Když má být dítě širší než čtyři, přidejte sloupce gridu, ne rozpětí
dítěti.

**Ta druhá past je Tailwind, ne tahle třída.** Třída gridu se skládá za běhu
z řetězců, takže ji scanner Tailwindu ve vašem kódu nevidí. Balíček každou možnou
utilitu `grid-cols-*` vypisuje jako literální text, aby ji scanner našel — což
funguje jen tehdy, když jsou view balíčku v vašich `content` cestách. Grid, který
se vykreslí se správným markupem a bez jediného sloupce, je skoro vždycky tohle.
Cesty najdete v [Getting Started](../../../start/getting-started.md).

## Základní použití

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name'),
        TextInput::make('last_name'),
    ])
```

`Grid::make()` nebere jméno, protože layout, který nenese stav, nemá čím být
adresovaný. Předejte ho, jen když ho chcete pro vlastní potřebu.

## Jak udělat jedno dítě širší

Dítě zabere víc, než mu náleží, přes `columnSpan()`, a celý řádek přes
`columnSpanFull()`:

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name'),
        TextInput::make('last_name'),
        Textarea::make('bio')->columnSpanFull(),   // [tl! focus]
    ])
```

Ve výchozím případě sahej po `columnSpanFull()`. Znamená „kolik je sloupců, tolik
si vezměte“, takže funguje dál, když grid ze dvou sloupců předěláte na tři.

## Responzivní sloupce

Když int přeskládání není to, co chcete, vyjmenuj každý breakpoint sám:

```php
Grid::make()
    ->columns([
        'default' => 1,   // telefony — jeden sloupec
        'md' => 2,        // tablety
        'xl' => 3,        // široké desktopy
    ])
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

Vygenerují se jen breakpointy, které pojmenujete, a každý platí až do dalšího.

## Zanořování

Gridy se zanořují, protože grid je jen další komponenta ve schématu. Takhle
dostane dvousloupcový formulář řádek, který je sám rozdělený na tři:

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('name'),
        TextInput::make('email'),
        Grid::make()->columns(3)->columnSpanFull()->schema([   // [tl! focus:start]
            TextInput::make('street'),
            TextInput::make('city'),
            TextInput::make('zip'),
        ]),                                                    // [tl! focus:end]
    ])
```

Vnitřní grid bere `columnSpanFull()`, aby zabral celý řádek toho vnějšího — bez
toho by se tři pole vmáčkla do jednoho ze dvou sloupců.

## Rozšířený příklad

Registrační formulář ve skutečném Livewire hostu. Grid je tu jediný layout,
všechno ostatní je obyčejné pole:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Grid::make()                                    // [tl! focus:start]
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email()->required()->columnSpanFull(),
                        Textarea::make('bio')->columnSpanFull(),
                    ]),                                          // [tl! focus:end]
            ])
            ->successMessage('Uživatel vytvořen');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Vytvořit</button>
</form>
```

Jeden sloupec na telefonu, dva od `md`, a e-mail i bio přes celý řádek v obou.

## Grid API

```php
->columns(int|array $columns)     // 2 (výchozí), nebo ['default' => 1, 'md' => 2, 'xl' => 3]
                                  // breakpointy: default|sm|md|lg|xl|2xl, počty ořezané na 1–12
->getColumns(): int|array
```

Všemu ostatnímu, čemu grid rozumí — `label()`, `visible()`, `hidden()`,
`columnSpan()`, `schema()` — je společný povrch, který nese každá schema
komponenta. Viz [Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Flex](flex.md) — jeden řádek, který si dělí prostor, když je pevný grid příliš tuhý
- [Section](section.md) — tytéž sloupce, plus nadpis a skládání
- [Fieldset](fieldset.md) — tytéž sloupce, uvnitř orámovaného legendu

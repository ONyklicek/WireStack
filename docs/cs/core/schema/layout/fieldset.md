---
order: 10
summary: HTML fieldset s legendou pro seskupení souvisejících komponent — týž layout ve formuláři i v infolistu.
---

# Fieldset

Skutečný HTML `<fieldset>` s `<legend>`. Sáhni po něm, když pár polí patří k sobě
a to seskupení je součástí *významu* — adresa, fakturační blok — ne jen vzhledu.
Čtečka obrazovky přečte legendu před každým polem uvnitř, a to je celý důvod, proč
ho upřednostnit před [`Gridem`](grid.md) s nadpisem nad ním.

```php
use NyonCode\WireCore\Foundation\Schema\Fieldset;
```

## Jak to funguje

Fieldset je **layoutová komponenta**: nenese hodnotu ani state path, takže se dá
přidat nebo odebrat bez dotyku na data. Vykreslí orámovaný box, dá `label()` do
legendy a děti uvnitř rozloží do gridu.

**Bez popisku není legenda.** Element `<legend>` se vypíše jen tehdy, když je popisek
nastavený, takže `Fieldset::make('address')` a nic dalšího vykreslí orámovaný box
bez nadpisu — což je legitimní přání a překvapení, když jste čekali, že se jméno
stane legendou. Na rozdíl od pole si fieldset ze jména popisek *neudělá*: napište
`->label('Adresa')`.

**Výchozí počet sloupců je 1**, ne 2 — fieldset je v první řadě seskupení a až
potom grid. Vyhodnocuje se různě podle tvaru, který předáte, a tohle je ta část,
kterou je dobré vědět:

- **Int** projde malou lokální mapou, která rozumí **1, 2, 3 a 4** a přeskládává se
  na `sm`, pak `md`, pak `lg`. `columns(6)` neodpovídá ničemu a děti se vykreslí
  v jednom sloupci úplně bez gridové třídy.
- **Mapa po breakpointech** projde tímtéž `ResponsiveGrid`, který používá
  [`Grid`](grid.md) — `['default' => 1, 'md' => 3]` — s plným rozsahem 1–12.

Ty dva tvary tady tedy nejsou jen dva zápisy téhož: **když chcete víc než čtyři
sloupce nebo kontrolu nad tím, kde se to přeskládá, předejte mapu.**

Děti se před vykreslením filtrují vlastní podmínkou `visible()` a fieldset se za
skrytým dítětem zavře.

## Základní použití

```php
Fieldset::make('address')
    ->label('Adresa')
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

## Rozložení skupiny

```php
Fieldset::make('address')
    ->label('Adresa')
    ->columns(3)                       // [tl! focus]
    ->schema([
        TextInput::make('street')->columnSpanFull(),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

Ulice zabere celý řádek, město a PSČ si rozdělí další. Na cokoli přes čtyři
sloupce nebo na jiný bod přeskládání použijte tvar s mapou:

```php
Fieldset::make('address')
    ->label('Adresa')
    ->columns(['default' => 1, 'lg' => 6])   // [tl! focus]
    ->schema([...])
```

## Vypnutí celé skupiny

`disabled()` je ze společného layoutového povrchu a propíše se na každé pole
uvnitř — jedna podmínka místo téže podmínky na každém poli:

```php
Fieldset::make('billing')
    ->label('Fakturační adresa')
    ->disabledWhen('same_as_shipping')   // [tl! focus]
    ->schema([
        TextInput::make('billing_street'),
        TextInput::make('billing_city'),
    ])
```

## Rozšířený příklad

Dva fieldsety ve skutečném Livewire hostu, kde se druhý vypne, jakmile je přepínač
nad ním zapnutý:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Fieldset;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditCustomer extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Customer::class)
            ->schema([
                Fieldset::make('shipping')                     // [tl! focus:start]
                    ->label('Doručovací adresa')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ship_street')->columnSpanFull(),
                        TextInput::make('ship_city'),
                        TextInput::make('ship_zip'),
                    ]),

                Toggle::make('same_as_shipping')->live(),

                Fieldset::make('billing')
                    ->label('Fakturační adresa')
                    ->columns(2)
                    ->disabledWhen('same_as_shipping')
                    ->schema([
                        TextInput::make('bill_street')->columnSpanFull(),
                        TextInput::make('bill_city'),
                        TextInput::make('bill_zip'),
                    ]),                                         // [tl! focus:end]
            ]);
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

Přepínač je `live()`, protože `disabledWhen` se vyhodnocuje při renderu — bez toho
by se fakturační blok nevypnul až do dalšího round tripu.

## Fieldset API

```php
->columns(int|array $columns)     // 1 (výchozí). int rozumí jen 1–4;
                                  // na cokoli jiného ['default' => 1, 'lg' => 6]
->getColumns(): int|array
```

Všechno ostatní — `label()`, `schema()`, `visible()`, `disabled()`,
`columnSpan()` — je společný layoutový povrch. Viz
[Společné API layoutů](../overview.md#spolecne-api-layoutu).

## Související

- [Schema](../overview.md) — slovník, do kterého tohle patří, a společný povrch
- [Grid](grid.md) — tytéž sloupce bez boxu a bez legendy
- [Section](section.md) — nadpis, popis a skládání místo legendy
- [Flex](flex.md) — jeden sdílený řádek místo sloupcového gridu

---
summary: Na desktopu jedno pole od–do nad kalendářem dvou měsíců, na telefonu dva provázané pickery — uloženo do dvou sloupců, s obdobími na jedno kliknutí.
---

# DateRangePicker

Období: dvě data, která patří k sobě, vybíraná jako jeden prvek a ukládaná do
dvou datumových sloupců, které aplikace už má. Sáhněte po něm pro platnost,
rezervaci nebo reportovací okno — všude, kde druhé datum dává smysl jen vedle
prvního.

```php
use NyonCode\WireForms\Components\DateRangePicker;
```

## Jak to funguje

**Dva pohledy na tytéž dva sloupce.** Od
[mobilního breakpointu](../../start/configuration.md#mobil) pole výš je rozsah
jedno pole s textem `01.10.2026 – 31.10.2026`, které otevře jeden panel: dva
měsíce vedle sebe, předvolby vedle nich a s `withTime()` hodiny pro každý konec.
Pod breakpointem vidí telefon dva pickery popsané níž — ovládání, které telefon
už dobře zvládá, beze změny. Obojí je v markupu, navázané na tytéž dvě stavové
cesty, a CSS na breakpointu ukáže jedno, takže ani jedno neví, které bylo
použito. Skrytá dvojice nese `aria-required` místo `required`, protože povinný
vstup schovaný stylem by zastavil odeslání formuláře.

**Dvojice se skládá, nepíše se znovu.** Každý konec je běžný
[DateTimePicker](date-time-picker.md) navázaný na vlastní sloupec, takže
kalendář, parser psaného vstupu, fallback na nativní input, mobilní sheet
i práce s časovými zónami jsou ty, které už to pole vlastní. Druhý kalendář
napsaný pro rozsahy by se s ním začal rozcházet hned v týdnu, kdy vznikl.

**Je to layout, ne pole, a právě to mu umožňuje ukládat se bez vlastního švu.**
Formulář dětské komponenty layoutu zplošťuje do seznamu polí, takže oba pickery
jsou pro plnění, validaci i ukládání běžná pole — období skončí ve dvou
skutečných datumových sloupcích, přesně jako by schéma vypsalo dva pickery. Žádný
polový stav, žádný JSON sloupec, žádný vlastní save hook.

**Třída vlastní jen to, co ani jeden konec nemůže vědět sám.** Provázání: koncový
picker se neotevře před počátečním datem a počáteční nepřejde přes koncové. Mez
je reaktivní, takže sleduje, co se vybralo na druhém konci, a dokud je ten konec
prázdný, spadne zpět na vlastní `minDate()` / `maxDate()` rozsahu.

**Totéž omezení je i pravidlo.** Mez v prohlížeči je zdvořilost — vložená
hodnota, zapomenutá záložka nebo vypnutý picker pořád mohou poslat konec před
začátkem — takže koncový picker nese `after_or_equal:<cesta začátku>` a období,
které končí dřív, než začíná, spadne na validaci místo aby se uložilo.

**Oba konce jsou live.** Uzávěry mezí se vyhodnocují na serveru, takže každý výběr
stojí jeden roundtrip; právě to nechá kalendář druhého konce překreslit s novým
limitem.

**Předvolby se počítají na serveru.** „Tento měsíc“ se spočítá v PHP a vykreslí
jako dvojice hodnot, takže prohlížeč nemusí vědět, co ta fráze znamená, a nikdy
se se serverem neneshodne na dnešním datu. Kliknutí zapíše oba konce najednou.

## Základní použití

```php
DateRangePicker::make('validity')
```

Dva date pickery nad `validity_from` a `validity_to`.

## Výběr období

Na desktopu první kliknutí na den období začne a druhé ho ukončí. Mezi nimi se
rozsah ukazuje pod ukazatelem myši — nebo pod fokusem klávesnice, který oběma
měsíci prochází stejnými klávesami jako
[panel DateTimePickeru](date-time-picker.md#desktopovy-panel). Druhý den před
prvním se čte jako období vybrané pozpátku a uloží se popředu; zavření panelu
s jediným vybraným koncem nechá období, jaké bylo.

Do pole jde i psát: dvě data ve formátu pole s pomlčkou mezi nimi
(`1. 9. 2026 – 15. 9. 2026`, nebo ` - ` s mezerami). Obě poloviny projdou stejnými
pravidly dnů jako kliknutí a odmítnutá kterákoli z nich vrátí poslední období.
Vyprázdnění pole vymaže oba konce.

## Pojmenování sloupců

```php
DateRangePicker::make('validity')
    ->from('valid_from')
    ->until('valid_to')
```

Vlastní jméno pole je úchyt pro popisek a id; do záznamu se dostanou jen tato dvě
jména.

## Meze

```php
DateRangePicker::make('validity')
    ->minDate('2026-01-01')
    ->maxDate(now()->addYear())
```

Každý konec čte mez rozsahu, dokud není vybraný ten druhý — a od té chvíle jeho
hodnotu. V desktopovém panelu jsou měsíce a dny mimo meze zašedlé a šipky se na
nich zastaví.

S časem jde omezit i hodiny každého dne — `minTime()` / `maxTime()` je
[denní časové okno](date-time-picker.md#denni-casove-okno), předané oběma koncům:

```php
DateRangePicker::make('shift')
    ->withTime()
    ->minDate('2026-10-01')
    ->maxDate('2026-12-31')
    ->minTime('06:00')         // oba konce mezi 06:00 a 18:00, každý den
    ->maxTime('18:00')
```

## Období s časem

```php
DateRangePicker::make('shift')
    ->from('starts_at')
    ->until('ends_at')
    ->withTime()               // oba konce vybírají datum i hodinu
```

Na desktopu je vedle kalendáře jeden [ciferník](time-picker.md#cifernik) s
přepínačem **Od / Do** nad ním, který ukazuje oba časy a říká, který konec
ciferník nastavuje; ciferník přichytává na `minutesStep()` pickerů, nastavuje
zvolený konec průběžně a Enter na minutách začátku přejde na konec. Nový začátek se otevře v 00:00 a nový konec na posledním
kroku svého dne, takže jednodenní období pokryje celý den; v jednodenním období
nesmí konec nikdy předběhnout začátek.

## Období na jedno kliknutí

```php
DateRangePicker::make('period')
    ->presets()                // dnes, tento týden, tento měsíc, posledních 30 dní, tento rok
```

```php
DateRangePicker::make('period')
    ->presets([
        'Q1' => ['2026-01-01', '2026-03-31'],
        'Q2' => ['2026-04-01', '2026-06-30'],
    ])
```

Vlastní sada nahradí vestavěnou. Každá hodnota je cokoli, co umí naparsovat
Carbon. Na desktopu jsou období seznam vedle kalendáře s označeným platným, na
telefonu čipy nad oběma pickery.

## Přístup k pickerům

```php
DateRangePicker::make('validity')
    ->configurePickers(fn (DateTimePicker $picker) => $picker
        ->firstDayOfWeek(1)
        ->disabledDates(['2026-12-24', '2026-12-25']))
```

Callback proběhne jednou za každý konec s jeho pickerem — únikový východ pro vše,
co tato třída znovu nevystavuje.

## Rozšířený příklad

```php
use Livewire\Component;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditContract extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Contract $contract): void
    {
        $this->form->fill($contract->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Contract::class)
            ->statePath('data')
            ->schema([
                TextInput::make('number')->required(),
                DateRangePicker::make('validity')   // [tl! focus:start]
                    ->label('Platnost')
                    ->from('valid_from')
                    ->until('valid_to')
                    ->minDate(today())
                    ->presets()
                    ->required(),                   // [tl! focus:end]
            ]);
    }
}
```

`fill()` přečte oba sloupce ze záznamu, protože jsou to běžná pole, a `save()` je
stejnou cestou zapíše zpátky.

## API DateRangePicker

Část pro rozsah. Všechno, co umí jeden konec — kalendář, nativní fallback,
formát, zakázané dny — patří [DateTimePickeru](date-time-picker.md) a dosáhnete
na to přes `configurePickers()`.

```php
->from(string $name)                        // sloupec začátku — výchozí '{name}_from'
->until(string $name)                       // sloupec konce — výchozí '{name}_to'
->fromLabel(?string $label)                 // výchozí: přeložené „Od"
->untilLabel(?string $label)                // výchozí: přeložené „Do"
->minDate(string|DateTimeInterface|Closure|null $date)  // fn ($get) => … pro reaktivní mez
->maxDate(string|DateTimeInterface|Closure|null $date)
->displayFormat(?string $format)            // tokeny PHP date(); uloženou hodnotu neovlivní
->withTime(bool $condition = true)          // oba konce vybírají datum i čas
->minTime(string|Closure|null $time)        // denní okno, 'H:i' — oba konce, každý den
->maxTime(string|Closure|null $time)
->required(bool $condition = true)          // vyžaduje oba konce
->presets(array $presets = [])              // ['popisek' => [od, do], …]; prázdné = vestavěná sada
->configurePickers(?Closure $callback)      // fn (DateTimePicker $picker) => …, jednou za konec
```

A co rozsah odpoví sám o sobě — včetně obou pickerů, pro volajícího, který
potřebuje sáhnout přímo na jeden z nich:

```php
->getFromName(): string
->getUntilName(): string
->getFromPicker(): DateTimePicker
->getUntilPicker(): DateTimePicker
->hasTime(): bool
->isRequired(): bool
->hasPresets(): bool
->getPresets(): array                       // ['popisek' => ['Y-m-d', 'Y-m-d'], …]
```

## Související

- [DateTimePicker](date-time-picker.md) — pole, kterým jsou oba konce
- [Formulářová pole](index.md) — sdílené API pole
- [Validace](../validation.md) — kde se pravidlo `after_or_equal` přidává k vašim

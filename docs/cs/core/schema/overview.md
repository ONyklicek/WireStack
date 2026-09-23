---
order: 1
summary: "Sdílený slovník pro rozvržení obsahu: jedno uspořádané pole komponent, které konzumují formuláře, infolisty i modaly akcí."
---

# Schema

**Schema** je uspořádané pole komponent předané do `->schema([...])`. Je to
sdílený slovník pro uspořádání obsahu a stejné komponenty se vykreslují
napříč povrchy — formuláře, infolisty i action modaly konzumují schema.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Section;

Section::make('profile')
    ->label('Profile')
    ->schema([
        Grid::make()->columns(2)->schema([
            TextInput::make('first_name'),
            TextInput::make('last_name'),
        ]),
    ])
```

## Jak to funguje

Schema je **strom komponent**. Ten strom tvoří dva druhy:

- **Pole** nesou hodnotu a state path — `TextInput`, `Select`, `Toggle`, …
  Váží se na váš model/stav a účastní se validace. Viz reference
  [Formuláře → Pole](../../forms/fields/index.md).
- **Layoutové a schema komponenty** nenesou vlastní stav; uspořádávají své
  potomky. `Grid`, `Section`, `Tabs`, `Wizard` a spol. každá bere svou vlastní
  `->schema([...])`, takže se layouty vnořují libovolně hluboko.

Při renderu hostitel prochází strom do hloubky: každá komponenta vyresolvuje svou
vlastní konfiguraci (popisky, viditelnost, sloupce) a vykreslí svůj Blade pohled,
rekurzivně do dětských schémat. Protože layoutové komponenty nedrží hodnotu, lze
je přidávat, odebírat nebo přeřazovat volně bez zásahu do vašich dat — na stav se
mapují jen pole.

Všechny schema komponenty žijí pod `NyonCode\WireCore\Foundation\Schema` a rozšiřují
sdílený základ `LayoutComponent`, což je důvod, proč identický `Grid` nebo `Section`
funguje ve formuláři, infolistu i modalu.

## Rozpětí sloupců

Jakýkoli potomek layoutu založeného na sloupcích (`Grid`, `Section`, `Fieldset`, `Tab`, `Step`)
řídí svou vlastní šířku:

```php
TextInput::make('bio')->columnSpan(2);      // rozpětí dvou sloupců
TextInput::make('notes')->columnSpanFull(); // rozpětí celého řádku
```

**Rozpětí se řeší proti mřížce, ve které komponenta skutečně skončí — na každé
šířce.** Mřížka je responzivní: `columns(3)` je na telefonu jeden sloupec a na
desktopu tři, takže rozpětí není jedna třída, ale žebřík. `columnSpan(3)`
v třísloupcovém `Gridu` je `md:col-span-3`, totéž rozpětí ve `Fieldsetu` (který má
dva sloupce už od `sm`) je `sm:col-span-2 md:col-span-3`. Píšeš číslo, breakpointy
si doplní mřížka.

**Rozpětí nikdy nemůže být širší než jeho mřížka.** Požádat o víc neznamená
oříznutou dlaždici — CSS Grid chybějící sloupec *přidá*, čímž přeskládá celý
layout a ostatní potomky zmáčkne do zbytku. Rozpětí širší než deklarovaný počet
se proto vykreslí jako celá šířka mřížky: `columnSpan(4)` ve dvousloupcové sekci
jsou dva sloupce, všude.

**Mřížka svým potomkům řekne, ve které mřížce jsou.** Komponenta se nikdy neptá,
kde sedí — dozví se to na vstupu a svůj žebřík si spočítá z toho, co jí bylo
řečeno. Všechny dodávané layouty to za tebe dělají, takže tohle je potřeba jen
když si píšeš vlastní layoutovou komponentu: předej stejný počet sloupců metodě
`ResponsiveGrid::cols()` pro mřížku a metodě `inGridOf()` každého potomka, a
rozpětí uvnitř se pak posouvají s mřížkou, ne mimo ni.

```php
@php
    use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

    $columns = $layout->getColumns();
@endphp

<div class="grid gap-4 {{ ResponsiveGrid::cols($columns) }}">
    @foreach ($layout->getSchema() as $component)
        @if ($component->isVisible())
            {{ $component->inGridOf($columns) }} {{-- [tl! focus] --}}
        @endif
    @endforeach
</div>
```

Potomek, kterému to nikdo neřekl, předpokládá mřížku přesně tak širokou, jaké
rozpětí si vyžádal. To je nejužší předpoklad, který nikdy nemůže vymyslet sloupec
— a zároveň to není mřížka, kterou jsi nakreslil, takže `columnSpan(2)` ve tvém
vlastním třísloupcovém layoutu by se přeskládalo na špatné šířce a nikdo by to
nenahlásil.

## Společné API layoutů

Každá layoutová komponenta — `Grid`, `Flex`, `Section`, `Fieldset`, `Tab`, `Step`,
`Tabs`, `Wizard` — dědí ze stejné báze `LayoutComponent`, takže tenhle povrch mají
všechny stejný a stránky jednotlivých komponent uvádějí jen to, co která přidává.

Vytvoří ji `Component::make(?string $name = null)`. Jméno je volitelné a **není**
state path: layout nenese hodnotu, takže jméno jen pojmenovává.

```php
->label(string|Closure|null $label)      // text nadpisu; fallback je Str::headline() jména
->hiddenLabel(bool $condition = true)    // komponentu zachová, nadpis nevykreslí
->schema(array $components)              // děti, v pořadí vykreslení
->statePath(?string $path)               // přepne kořen state path pro všechno pod ní
->columnSpan(int|string $span)           // 2|3|4|'full' — kolik z RODIČOVSKÉHO gridu zabere
->columnSpanFull()                       // zkratka pro 'full'
->inGridOf(int|array $columns)           // mřížka, ve které se komponenta kreslí; layout to řekne každému potomkovi
->visible(bool|Closure $condition = true)
->hidden(bool|Closure $condition = true)
->visibleWhen(string $field, mixed $value = true)   // vidět, dokud se jiné pole rovná $value
->hiddenWhen(string $field, mixed $value = true)
->disabled(bool|Closure $condition = true)          // vypne každé pole pod sebou
->disabledWhen(string $field, mixed $value = true)
->livewire(mixed $livewire)              // host; nastaví se sám, když formulář připravuje děti
->getName(): string
->getLabel(): ?string
->getSchema(): array
->getColumnSpan(): int|string|null
->isVisible(): bool
->isHidden(): bool
->isDisabled(): bool
```

Tři z nich si zaslouží větu, protože právě na ně lidi narazí jako na překvapení:

- **`columnSpan()` je o rodiči, ne o dítěti.** Říká, kolik z gridu, který tuhle
  komponentu *obsahuje*, zabere — omezené tím, co ta mřížka má: `columnSpan(5)`
  ve čtyřsloupcové mřížce jsou čtyři sloupce, ve dvousloupcové dva.
  `columnSpanFull()` je celý řádek, ať už je řádek jakýkoli.
- **`visible()` bere closure a vyhodnocuje se při každém renderu**, takže layout
  může přicházet a mizet podle stavu formuláře. `visibleWhen('type', 'company')`
  je totéž napsané pro ten obvyklý případ.
- **`disabled()` se propisuje dolů.** Vypnutí sekce vypne každé pole uvnitř, což je
  jedna podmínka místo téže podmínky na každém poli.

## Layoutové komponenty

| Komponenta | Účel |
|-----------|---------|
| [Grid](layout/grid.md) | Responzivní vícesloupcový layout |
| [Flex](layout/flex.md) | Uspořádat potomky na jedné vodorovné (flexbox) ose |
| [Section](layout/section.md) | Seskupit komponenty pod nadpisem, volitelně sbalitelné |
| [Fieldset](layout/fieldset.md) | Seskupit související komponenty s ohraničenou legendou |
| [Tabs](layout/tabs.md) | Client-side záložkové panely (všechny panely validují společně) |
| [Wizard](layout/wizard.md) | Client-side vícekrokový layout s indikátorem kroků |

## Prime komponenty

Statické, ne-vstupní komponenty, které zobrazují obsah:

| Komponenta | Účel |
|-----------|---------|
| [Callout](callout.md) | Jemný, barevný upozorňovací box s nadpisem a ikonou |
| [Empty State](empty-state.md) | Vycentrovaný placeholder zobrazený, když není co zobrazit |

## Kde se schémata používají

Protože tyto komponenty žijí v core `Foundation\Schema`, konzumuje je víc
než jen formuláře:

- **Formuláře** staví své tělo ze schématu, a to přímo z těchhle tříd. Tenké
  podtřídy `NyonCode\WireForms\Components\Layout\*`, které vyměňovaly
  form-specifický markup, byly ve 2.0 odstraněny: jejich kopie views zaostaly za
  originály, takže sekce ve formuláři vykreslila míň než sekce v infolistu
  postavená ze stejné třídy.
- **Infolisty** znovupoužívají stejný layout slovník pro read-only detailní pohledy.
- **Action modaly** používají [Wizard](layout/wizard.md) pro vícekrokové toky — viz
  [Modaly → Vícekrokový wizard](../modals.md#vicekrokovy-wizard).

## Související

- [Formuláře](../../forms/overview.md) — povrch, který si tělo staví ze schématu
- [Infolisty](../infolists/index.md) — tytéž layouty, jen ke čtení
- [Pole](../../forms/fields/index.md) — komponenty, které stav opravdu nesou
- [Modaly](../actions/modals.md) — modaly akcí, které schéma konzumují taky

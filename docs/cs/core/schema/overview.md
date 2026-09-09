---
order: 1
summary: "Sdílený slovník pro rozvržení obsahu: jedno uspořádané pole komponent, které konzumují formuláře, infolisty i modály akcí."
---

# Schema

**Schema** je uspořádané pole komponent předané do `->schema([...])`. Je to
sdílený slovník pro uspořádání obsahu a stejné komponenty se vykreslují
napříč surface — formuláře, infolisty i action modaly konzumují schema.

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
vlastní konfiguraci (labely, viditelnost, sloupce) a vykreslí svůj Blade pohled,
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
  komponentu *obsahuje*, zabere. Rozumí `2`, `3`, `4` a `'full'` a ničemu jinému —
  `columnSpan(5)` tiše znamená „jeden sloupec".
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

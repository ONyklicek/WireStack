---
order: 1
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

- **Formuláře** staví své tělo ze schématu. `Grid`, `Section` a `Fieldset`
  mají také tenké aliasy `NyonCode\WireForms\Components\Layout\*` (deprecated ve
  v2.0), které jen vymění form-specifický markup; každá další schema komponenta se
  používá přímo.
- **Infolisty** znovupoužívají stejný layout slovník pro read-only detailní pohledy.
- **Action modaly** používají [Wizard](layout/wizard.md) pro vícekrokové toky — viz
  [Modaly → Vícekrokový wizard](../modals.md#vicekrokovy-wizard).

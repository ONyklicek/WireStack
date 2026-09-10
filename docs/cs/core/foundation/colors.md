---
order: 30
summary: "Jeden resolver, kterým prochází každý barevný povrch — slovník, fallbacky a proč se odznak a tlačítko shodnou."
---

# Barvy

Barva je v tomhle frameworku **sémantický název** — `success`, `danger`, název z
Tailwind palety — který na utility třídy překládá jeden vlastník. Ptá se ho
odznak, tlačítko, toast i položka grafu, a proto se nemůžou rozejít — a taky
proto dostane nový povrch celou paletu zadarmo.

## Barvy

`->color()` přijímá **kompletní Tailwind paletu** na každém povrchu. Dva slovníky
resolvují přes stejnou kanonickou mapu:

<div class="wire-swatches"><p class="wire-swatch-group">Sémantické role</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: var(--primary)"></span><span class="wire-swatch__name">primary</span><span class="wire-swatch__alias">váš akcent</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6b7280"></span><span class="wire-swatch__name">gray</span><span class="wire-swatch__alias">secondary</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #10b981"></span><span class="wire-swatch__name">success</span><span class="wire-swatch__alias">emerald</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">danger</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f59e0b"></span><span class="wire-swatch__name">warning</span><span class="wire-swatch__alias">amber</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">info</span></div></div><p class="wire-swatch-group">Základní odstíny</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #3b82f6"></span><span class="wire-swatch__name">blue</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #22c55e"></span><span class="wire-swatch__name">green</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ef4444"></span><span class="wire-swatch__name">red</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #eab308"></span><span class="wire-swatch__name">yellow</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #06b6d4"></span><span class="wire-swatch__name">cyan</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #64748b"></span><span class="wire-swatch__name">slate</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #71717a"></span><span class="wire-swatch__name">zinc</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #737373"></span><span class="wire-swatch__name">neutral</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #78716c"></span><span class="wire-swatch__name">stone</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f97316"></span><span class="wire-swatch__name">orange</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #84cc16"></span><span class="wire-swatch__name">lime</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #14b8a6"></span><span class="wire-swatch__name">teal</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #0ea5e9"></span><span class="wire-swatch__name">sky</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #6366f1"></span><span class="wire-swatch__name">indigo</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #8b5cf6"></span><span class="wire-swatch__name">violet</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #a855f7"></span><span class="wire-swatch__name">purple</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #d946ef"></span><span class="wire-swatch__name">fuchsia</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ec4899"></span><span class="wire-swatch__name">pink</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #f43f5e"></span><span class="wire-swatch__name">rose</span></div></div><p class="wire-swatch-group">Achromatické (adaptivní)</p><div class="wire-swatch-grid"><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #ffffff; box-shadow: inset 0 0 0 1px #d1d5db"></span><span class="wire-swatch__name">white</span></div><div class="wire-swatch"><span class="wire-swatch__chip" style="--swatch: #000000"></span><span class="wire-swatch__name">black</span></div></div></div>

**Sémantické role** — pevné brand odstíny nesoucí význam:

| Název | Resolvuje na |
|------|-------------|
| `primary` | Brand primary |
| `success` (alias `emerald`) | Emerald |
| `danger` | Red |
| `warning` (alias `amber`) | Amber |
| `info` | Cyan |
| `gray` (alias `secondary`) | Neutrální šedá |

**Surové rodiny odstínů** — každá Tailwind barva, pro jemnější kontrolu:

`blue`, `green`, `red`, `yellow`, `cyan`, `slate`, `zinc`, `neutral`, `stone`,
`orange`, `lime`, `teal`, `sky`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`,
`rose`.

> **Literal odstíny nejsou aliasy.** `blue`, `green` a `yellow` jsou vlastní
> literal Tailwind odstíny — `blue` je odlišný od přebarvitelného brand `primary`,
> `green` od `success`/`emerald` a `yellow` od `warning`/`amber`. `red` a `cyan`
> vykreslí stejný odstín jako `danger`/`info`, ale zůstávají dostupné pod svým jménem.

**Achromatické krajní body** — `white` a `black`. Tailwind nemá číselnou škálu
`white`/`black`, takže se resolvují **adaptivně**: `black` je tmavá výplň/inkoust ve
světlém režimu a překlopí se na bílou v tmavém, `white` je opačně — takže zůstanou
čitelné v obou motivech.

```php
Action::make('delete')->color('danger');   // sémantická role
Action::make('archive')->color('teal');     // surový odstín
BadgeColumn::make('status')->colors([
    'active' => 'success',
    'pending' => 'warning',
    'inactive' => 'danger',
]);
```

Type-safe enum `Foundation\Colors\Color` má case pro každou z těchto
(`Color::Danger`, `Color::Teal`, …). Každá barva resolvuje na Tailwind utility třídy
pro bg, text, border, ring a hover varianty — stejná hodnota se vykreslí identicky
na badge, solid/outlined/link tlačítku, modalu, choice kartě a chart baru.

<a id="canonical-color-resolvers-hascolor"></a>
### Kanonické color resolvery (`HasColor`)

`Foundation\Concerns\HasColor` jsou **dveře**, kterými se ptá každý povrch. Ptejte
se jich místo re-enkódování `match` mapy a barva se vyřeší všude stejně.

Samotná pravidla bydlí v pěti třídách pod `Foundation\Colors`, jedna na povrch —
`ButtonPalette`, `TintPalette`, `TextPalette`, `NoticePalette` a `ChartPalette` —
protože rozhodnutí o alertu je jiné rozhodnutí než rozhodnutí o grafu.
**Volejte dál `HasColor`**: palety jsou místo, kde se pravidlo najde a změní, ne
druhá sada jmen k volání.

**Role není odstín.** `success`, `danger`, `warning` a `info` se vykreslí jako to,
na co je nasměruje `wire-core.colors`, vyřešeno jednou v
`Foundation\Colors\SemanticPalette` na začátku každého resolveru — viz
[Vzhled → Sémantické role](../../start/theming.md#semanticke-role). Literal
odstíny zůstávají první třídy: `green` je literální zelená, odlišná od
přesměrovatelného `success`, a adaptivní krajní body `white`/`black` resolvují
samy na sebe.

| Resolver | Surface |
|----------|---------|
| `getSolidColorClasses()` | vyplněné tlačítko (bg + text + hover + focus + dark) |
| `getOutlinedColorClasses()` | outlined tlačítko |
| `getGhostColorClasses()` | rozbalovací nabídka / položka menu |
| `getIconButtonColorClasses()` | tlačítko jen s ikonou |
| `getLinkColorClasses()` | text/link tlačítko (podtržení při hoveru) |
| `getSolidBgClass()` / `getSoftBgClass()` | jen holá výplň (dráha toggle on/off, count badge) |
| `getBadgeColorClasses()` | soft „pill“ badge (bg + text) |
| `getTextColorClasses()` | jen foreground text tint |
| `getChoiceColorClasses()` | balík selected stavu radio/segmented/karta |
| `getModalSubmitButtonClasses()` | modal confirm/submit tlačítko |
| `getModalIconBgClass()` / `getModalIconTextClass()` | modal icon chip |
| `getGradientFillClasses()` / `getFillTextClasses()` | bar-chart výplň + akcent (literal chart odstíny) |
| `getOutlinedClasses()` | orámovaný slovník, volatelný mimo komponentu |
| `getRowTintClasses()` / `getRowHoverClasses()` | klikatelný řádek tabulky, v klidu a pod ukazatelem |
| `getSoftTintClasses()` | klidová výplň bez hoveru — buňka diffu, měkký blok |
| `getAccentBgClass()` | jasný akcent `-500` — živá tečka, progress bar, vyplněná hvězda |

Při přidávání barvy nebo povrchu rozšiřte jednou paletu, která ji vlastní — navazující
sloupce, badge, akce a toggly ho vyzvednou automaticky. Udržujte utility
názvy kompatibilní s nejnižší podporovanou verzí Tailwindu (viz
[ADR 0005](https://github.com/ONyklicek/WireStack/blob/main/architecture/decisions/0005-tailwind-4-support.md)); používejte jen
standardní názvy odstínů, nikdy verzí-specifické.

### Kanonické resolvery velikosti a typografie

Sourozenecké single-source resolvery, používané stejně jako `HasColor` — rozšiřte jednou,
každý povrch je vyzvedne a řetězce tříd zůstanou literální pro Tailwind JIT
scanner.

| Resolver | Surface |
|----------|---------|
| `HasSize::getBadgeSizeClasses($size)` | padding + velikost písma soft „pill“/badge |
| `HasSize::getButtonSizeClasses($size, $iconOnly)` | škála paddingu tlačítka (akční tlačítka, triggery skupin akcí, `ButtonColumn`); `$iconOnly` vrací čtvercový padding |
| `HasFontWeight::getFontWeightClasses($weight)` | `font-*` weight utility (sloupce tabulky, infolist entries); neznámý weight → `font-normal` |
| `Foundation\Concerns\HasModalProperties::getMaxWidthClass($width, $responsive)` | modal `max-w-*` (vycentrované dialogy gatují na `sm:`; slide-overy předávají `responsive: false`) |

### Type-safe hodnotové enumy

Každý fluent setter, který bere řetězcový token, **přijímá také kanonický enum** z
`Foundation\Enums\` — `->size('lg')` a `->size(Size::Lg)` jsou zaměnitelné a
řetězcová forma zůstává plně podporovaná. Každý enum je jediný vlastník svého slovníku
(`values()` + `resolve()`), takže token resolvuje na stejnou utility na každém povrchu a
neznámé tokeny spadnou na rozumný výchozí místo emitování nescannovatelné třídy.

| Enum | Tokeny | Settery, které ho přijímají |
|------|--------|------------------------|
| `Colors\Color` | sémantické role + každý surový odstín (viz [Barvy](#barvy)) | `->color()` všude |
| `Enums\Breakpoint` | `sm` `md` `lg` `xl` `2xl` | sloupec `->visibleFrom()` / `->hiddenFrom()` / `->mobileBreakpoint()`, `Table::stackedOnMobile()`, `->mobileBreakpoint()` na sheetech/modalech, klíče `columns` v `Grid` podle breakpointů |
| `Enums\Size` | `xs` `sm` `md` `lg` `xl` | `->size()` (+ zkratky `->sm()`/`->md()`/…) na akcích, tlačítkách, badge/icon sloupcích |
| `Enums\FontWeight` | `thin` `extralight` `light` `normal` `medium` `semibold` `bold` `extrabold` `black` | sloupec `->weight()`, infolist `TextEntry::weight()` |
| `Enums\Alignment` | `left` `center` `right` | sloupec `->alignment()`, `Table::actionsAlignment()` |
| `Enums\IconPosition` | `before` `after` | `->icon($icon, $position)` na akcích, tlačítkách, polích |
| `Enums\Placement` | `bottom-start` `bottom-end` `top-start` `top-end` | `ActionGroup::dropdownPosition()` |
| `Enums\ModalWidth` | `sm` `md` `lg` `xl` `2xl` … `7xl` `full` | `->width()` / `->modalWidth()` na modalech, slide-overech, action modalech |

```php
use NyonCode\WireCore\Foundation\Enums\{Alignment, Breakpoint, ModalWidth, Size};

TextColumn::make('email')->visibleFrom(Breakpoint::Md)->alignment(Alignment::Right);
Action::make('edit')->size(Size::Lg)->modalWidth(ModalWidth::TwoXl);
```

Enumy `Breakpoint`, `Alignment` a `Placement` navíc vlastní **literální** Tailwind
třídy, na které jejich tokeny mapují (`Breakpoint::Md->tableCellClass()`, `Alignment::Right->textClass()`,
`Placement::TopEnd->originClass()`), takže mapa tříd má jednoho vlastníka a Blade konzumuje scannovatelnou
utility místo interpolace `text-{$align}`.

<a id="enums"></a>

## Související

- [Ikony](icons.md) — druhá polovina slovníku povrchu
- [Enumy](enums.md) — enum, který si pojmenuje vlastní barvu
- [Motivy a přizpůsobení](../../start/theming.md) — změna palety, kterou aplikace používá
- [BadgeColumn](../../table/columns/badge.md) — rozhodovací žebříček na skutečném povrchu

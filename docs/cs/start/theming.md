---
order: 50
summary: Deset úrovní přizpůsobení, od zúžení tématu na admin po publikované view, a co která stojí při upgradu.
---

# Vzhled a přizpůsobení

Wire dodává nestylovaný-ale-rozumný Tailwind markup s plnými dark-mode variantami.
Vzhled si přizpůsobíte na deseti úrovních, od nejlehčí po nejtěžší:

| Úroveň | Dosah | Náročnost |
|-------|-------|--------|
| [Rozsah](#rozsah) | Zda se téma dotkne i veřejného webu | Druhý stylesheet |
| [Barvy](#barvy) | Akcentová a neutrální paleta všude | Tailwind config |
| [Sémantické role](#semanticke-role) | Co se vykreslí jako `success`/`danger`/`warning`/`info` | `wire-core` config |
| [Tvar](#tvar) | Zaoblené rohy, nebo hranaté | `wire-core` config |
| [Hustota](#hustota) | Kolik místa si rozhraní dává | `wire-core` config, nebo přepínač |
| [Stylovací hooky](#stylovaci-hooky) | Jeden druh prvku, kdekoli se objeví | Vlastní CSS |
| [Render hooky](#render-hooky) | Přidání něčeho, co tam není | Closure vracející view |
| [Ikony](#ikony) | Výměna nebo přidání ikon globálně | `wire-core` config |
| [Per-komponenta](#upravy-per-komponenta) | Jedno pole/sloupec/akce | Fluent API |
| [Přepis pohledů](#prepis-pohledu) | Markup libovolné komponenty | Publish + editace Blade |

---

<a id="scope"></a>
## Rozsah

Tohle rozhodněte dřív než cokoli jiného, protože to mění, kam všechny ostatní
úrovně dopadnou. Admin panel má obvykle vypadat jinak než veřejný web před ním —
a řekne se to tím, **který stylesheet admin nahraje**, ne obalovou třídou ani
selektorem.

Layout shellu název vstupu neuhaduje; vykreslí to, co dáte do jeho slotu `head`.
Dejte tedy adminu vlastní:

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/admin.css', 'resources/js/app.js'])
    </x-slot:head>

    {{ $slot }}
</x-wire-admin::layout>
```

```css
/* resources/css/admin.css — kompiluje se pro admin stránky, a nikde jinde */
@import "tailwindcss";
@custom-variant dark (&:where(.dark, .dark *));
@source "../../vendor/nyoncode";

@theme {
    --radius-sm: 0; --radius-md: 0; --radius-lg: 0;   /* hranaté rohy */
    --spacing: 0.2rem;                                /* těsněji všude */
    --color-primary-500: var(--color-violet-500);
}
```

Veřejné stránky dál nahrávají `app.css` a nic z toho nevidí. Nic z toho není
funkce Wire — je to Tailwind 4, který čte vlastní theme proměnné, a proto každá
utilita, kterou tenhle framework píše, následuje bez jediného publikovaného view.

Dvě věci k vědomí:

- Druhý vstup potřebuje vlastní `@source "../../vendor/nyoncode"`. Bez něj
  Tailwind nikdy neproskenuje views balíčků a admin se vykreslí bez stylů, aniž
  by se kdekoli objevila chyba.
- Na admin stránce nahrávejte jeden, nebo druhý, ne oba.

> **Tailwind 3.** Poloměr a odsazení jsou tam zkompilované hodnoty, ne proměnné,
> takže blok `@theme` nastaví vlastnosti, které nikdo nečte: žádná chyba, žádná
> změna. Barvy a sémantické role fungují na obou verzích.

---

<a id="colors"></a>
## Barvy

Komponenty Wire stojí na dvou Tailwind škálách barev: **`primary`** (akcent —
tlačítka, focus ringy, aktivní stavy) a **`gray`** (plochy, ohraničení, text).
`primary` je **povinná** — bez ní se interaktivní prvky vykreslí neviditelně.

Definujte ji v konfiguraci Tailwindu podle
[Začínáme → Barva primary](getting-started.md#barva-primary). Stručně:

```js
// tailwind.config.js (Tailwind 3)
const colors = require('tailwindcss/colors')

module.exports = {
    theme: {
        extend: {
            colors: { primary: colors.indigo },
        },
    },
}
```

Chcete-li přestylovat neutrály (například teplejší UI), nasměrujte `gray` na
jinou Tailwind škálu jako `colors.zinc` nebo `colors.slate` stejným způsobem.

> Protože je paleta řízena zcela vaší Tailwind konfigurací, vlastní téma je
> změna konfigurace — neupravujete CSS balíčku.

---

<a id="semantic-roles"></a>
## Sémantické role

`success`, `danger`, `warning` a `info` jsou role, ne barvy. Co se za každou
vykreslí, je odstín, který si zvolíte, a následuje ho každá plocha — tlačítka,
badge, alerty, ikony modálů, toasty, tinty řádků tabulky i audit timeline:

```php
// config/wire-core.php
'colors' => [
    'success' => 'teal',    // místo dodávané emerald
    'danger' => 'rose',
    'warning' => 'amber',
    'info' => 'cyan',
],
```

Hodnota je **odstín, na který tenhle framework už třídy dodává**, nikdy název
třídy a nikdy CSS proměnná. Třída pojmenovaná v config souboru není soubor, který
Tailwind skenuje, takže by se tiše nikdy nezkompilovala; proměnná by vyžadovala
Tailwind 4 a token, který si vaše aplikace nezapomene definovat. Odstín dopadne
do `match` arm, jejíž třídy jsou už teď literální — proto tohle funguje na
Tailwindu 3 i 4.

Odstín, který nikdo nezná, si nechá dodávanou výchozí hodnotu místo aby zšedl,
takže překlep vás stojí tu změnu, ne barvu.

`primary` tu záměrně není: přesměrujte `--color-primary-*` ve vlastním bloku
`@theme`, jak ukazují [Barvy](#barvy).

---

<a id="shape"></a>
## Tvar

Jak jsou seříznuté rohy. Dvě nastavení, `rounded` a `sharp`:

```php
// config/wire-core.php
'shape' => 'sharp',
```

Karty, pole, tlačítka i shell zhranatí naráz — 34 rohů na stránce uživatelů,
z jednoho řádku.

### Jsou to dvě pravidla a to druhé je zajímavější

Vynulování radius tokenů zhranatí všechno, co Tailwind kompiluje na
`var(--radius-*)`. Nezhranatí **žádnou** pilulku: `rounded-full` se kompiluje na
`calc(infinity * 1px)` a nečte žádný token. Badge, tagy **a chrome shellu** —
vyhledávání, přepínače, tlačítko uživatele — se proto hranatí jmenovitě, přes
[stylovací hooky](#stylovaci-hooky). A **avatary zůstávají kulaté záměrně**.

Ta hranice je celá pointa: **obsah a chrome se hranatí, tváře ne.** Badge je
hodnota s podkladem, vyhledávání je nábytek, avatar je obrázek člověka — a
zhranatit ho čte jako rozbité, ne jako ostré. Žádný token tenhle rozdíl vést
neumí, a proto má framework tokenovou i hookovou vrstvu; `wire-core::partials.shape`
je místo, kde se potkají. Když chcete hranaté i avatary, je to jedno pravidlo
navíc ve vašem stylesheetu:

```css
[data-wire="admin-avatar"] { border-radius: 0; }
```

### Bez přepínače, záměrně

Na rozdíl od [hustoty](#hustota) tvar přepínač pro jednotlivce nemá. Hustota je
pracovní preference — jeden chce na obrazovce víc řádků než druhý. Tvar je
identita, a admin, který je pro jednoho kolegu kulatý a pro druhého hranatý,
jsou dva produkty, ne jedna respektovaná preference.

> **Tailwind 3.** Tvar potřebuje `--radius-*`, který Tailwind 3 nevydává. Viz
> [Rozsah](#rozsah).

---

<a id="density"></a>
## Hustota

Kolik místa si rozhraní dává. Dvě nastavení, `normal` a `compact`:

```php
// config/wire-core.php
'density' => 'compact',
```

To je celá fixní půlka. Řádek tabulky spadne z 65 px na 52 a pole create
formuláře z 550 px na 446, přičemž písmo zůstane přesně tam, kde bylo — velikost
písma jede přes vlastní tokeny, takže compact stáhne chrome a slova nechá být.

### Jsou to tři změny, ne jedna

Stojí za to je znát, protože dvě z nich nejsou samozřejmé a jinak byste je
objevovali celé odpoledne:

- **Klesne `--spacing`.** Každá utilita paddingu, mezery i velikosti se
  kompiluje na `calc(var(--spacing) * n)`, takže tohle řádek opravdu stáhne.
- **Horní lišta a ikony jsou přišpendlené zpátky.** Ten samý token řídí `h-16`
  i `w-4`. Bez pojistky vezme compact horní lištu shellu ze 64 px na 44 a ikonu
  z 16 px na 10 — v media manageru na 8, kde s sebou stáhne i ikonová tlačítka.
  To není hustota, to je vada.
- **Ovládací prvky formulářů se řeší jmenovitě.** `@tailwindcss/forms` zapisuje
  `padding: .5rem .75rem` na každý text input, select i textarea jako literál ve
  své base vrstvě, takže na plochu, kde hustota znamená nejvíc, žádný token
  nedosáhne.

Tabulka má vlastní `->compact()` a ta dvě nastavení se skládají, místo aby se
nahrazovala: celoaplikační compact vezme řádek z 65 px na 51, tabulka, která si
navíc řekne o `->compact()`, jde na 40. Je to záměrné „tahle tabulka je zvlášť
hustá", ne omylem dvakrát použité nastavení — ale stojí za to o tom vědět, než
napíšete obojí.

Všechno je v `wire-core::partials.density`, který shell dává do hlavičky.
**Vlastní layout ho musí includovat**, stejně jako musí nést `@wireStackScripts`:

```blade
@include('wire-core::partials.density')
```

### Když má volit člověk

Shell dodává přepínač vedle přepínače tématu a ta dvě nastavení se skládají,
místo aby se přetahovala: **váš config je výchozí, volba člověka ho přebíjí.**
Kdo se přepínače nikdy nedotkne, dostane to, co jste nastavili — včetně pozdější
změny té hodnoty.

Volba je per prohlížeč, uložená v `localStorage`, aplikovaná před prvním
vykreslením a znovu po `wire:navigate` — Livewire kopíruje `<html>` atributy
staženého dokumentu přes živé, takže bez té poslední části by každý přechod
volbu tiše zahodil.

Když si to chcete řídit sami:

```js
window.wireDensity.set('compact');   // zvolit
window.wireDensity.clear();          // zpět na výchozí hodnotu aplikace
```

> **Tailwind 3.** Hustota potřebuje `--spacing`, který Tailwind 3 nevydává —
> pravidla nastaví vlastnost, kterou nikdo nečte, takže stránka zůstane
> nezměněná a nic nespadne. Viz [Rozsah](#rozsah).

---

<a id="styling-hooks"></a>
## Stylovací hooky

Úrovně výše posouvají hodnotu všude. Tahle přestyluje **jeden druh prvku** —
sidebar, toolbar tabulky, každý badge — a sáhnete po ní místo publikování view.

Každý smysluplný prvek nese stabilní jméno:

```html
<aside data-wire="admin-sidebar" class="…">
```

Napište na něj CSS ve vlastním stylesheetu. `@apply` funguje včetně dark variant
a pseudo-tříd:

```css
/* resources/css/admin.css */
[data-wire="admin-sidebar"] { @apply bg-gray-50 dark:bg-gray-950; }
[data-wire="table-row"]:hover { @apply bg-primary-50; }
[data-wire="badge"] { @apply font-mono tracking-tight; }
```

Nic se nepublikuje, takže nic neforkuje. Příští vydání může kolem prvku přidat
markup a vaše pravidlo dál platí — a přesně to publikované view slíbit nemůže.

### Jak jméno najít

Otevřete devtools a podívejte se na prvek, stejně jako u kteréhokoli webu. Jména
jsou přímo na elementech. Hromadně:

```bash
grep -rho 'data-wire="[a-z-]*"' vendor/nyoncode | sort -u
```

### Kotvy, které stojí za to znát

Framework jich nese pár set; tyhle jsou ty, od kterých většina témat začíná:

| Jméno | Prvek |
|------|---------|
| `admin-sidebar`, `admin-topbar`, `admin-content` | Tři oblasti shellu |
| `admin-nav-item`, `admin-nav-label`, `admin-nav-badge-dot` | Jedna položka menu |
| `table-search`, `table-toolbar`, `table-bulk-bar` | Chrome nad tabulkou |
| `form-field` | Wrapper, který má každé pole formuláře |
| `badge`, `button`, `callout`, `dropdown`, `menu-item`, `section` | Sdílené komponenty, kdekoli se objeví |

### Co jméno slibuje

- **Jméno je veřejné API.** Může přibýt; nepřejmenuje se ani nezmizí v minor
  verzi.
- **Jména popisují věc, nikdy její vzhled** — `table-toolbar`, nikdy
  `table-grey-bar`. Jméno popisující, jak něco vypadá, by se muselo přejmenovat,
  jakmile tak vypadat přestane.
- **Framework na ně sám nikdy nepíše pravidlo.** Jsou to holé cíle, takže vaše
  pravidlo nemá co přebíjet a `!important` nikdy nepotřebuje.
- **`data-wire` není `data-testid`.** Testovací atribut existuje, aby test našel
  prvek, a musí zůstat volný ke změnám z testovacích důvodů. Kde jsou oba, nesou
  záměrně stejné jméno — ale kontraktem je jen `data-wire`.

---

<a id="render-hooks"></a>
## Render hooky

Všechny úrovně výše mění, jak něco *vypadá*. Tahle dá na stránku něco, co tam
nebylo — badge za nadpisem stránky, poznámku pod menu, tlačítko vedle
vyhledávání v tabulce.

```php
// Service provider.
use NyonCode\WireCore\Core\Plugin\RenderHook;

RenderHook::add('panels.page.header.end', fn (array $scope) => view('badges.beta', $scope));
```

Callback se spustí tam, kde ta pozice v markupu sedí, a jeho výsledek se tam
vykreslí. Nic se nepublikuje, takže nic neforkuje.

### Pozice

| Pozice | Kde |
|----------|-------|
| `admin.topbar.end` | Za posledním ovládacím prvkem v horní liště shellu |
| `admin.sidebar.end` | Pod menu, nad spodním okrajem sidebaru |
| `panels.page.header.end` | Za nadpisem a popisem stránky |
| `table.toolbar.end` | Za vyhledáváním a filtry, před akcemi |

**Čtyři není startovní sada k doplnění.** Pozice existuje, protože ji něco
potřebovalo; framework nepřidává pozici pro symetrii s jinou, která už existuje,
a balíček nad ním by neměl taky. Když potřebujete pozici, která tu není, stojí za
to to říct — přidané jméno je jméno, které už nikdy nemůže tiše zmizet.

Jména se čtou zvenku dovnitř: `panels.page.header.end` je balíček panels, jeho
chrome stránky, hlavička, na konci.

### Co smí callback vrátit

| Návratová hodnota | Co se stane |
|--------|--------------|
| `View` nebo `Htmlable` | Vykreslí se tak, jak je. Tenhle tvar používejte. |
| `string` | **Escapuje se.** Text jde dovnitř jako text. |
| cokoli jiného včetně `null` | Ignoruje se — callback, který nemá co přidat, to řekne tím, že nevrátí nic. |

Holý string se escapuje záměrně: markup musí být view nebo výslovný
`HtmlString`, takže nic nevloží tag z hodnoty, kterou jste nepsali.

### Pořadí a rozsah

Registrace je ta samá, jakou používá každý lifecycle hook, takže bere stejné dvě
volby:

```php
RenderHook::add('table.toolbar.end', $render, priority: -10);       // běží první
RenderHook::add('table.toolbar.end', $render, for: 'invoices');     // jen tento resource
```

Callbacky běží v pořadí priorit a jejich výstup se v tom pořadí spojuje, takže
dva balíčky přidávající na jednu pozici si udrží dané pořadí.

Pozice, na kterou se nikdo neregistroval, stojí jedno vyhledání v poli a vykreslí
prázdný řetězec — proto můžou sedět v markupu, který se renderuje na každé
stránce.

---

<a id="icons"></a>
## Ikony

Ikony se resolvují přes core `IconManager`, konfigurovaný v
`config/wire-core.php`. Přibalená sada Heroicons je **neprefixovaný výchozí**;
další sady jsou pod prefixem a používají se společně.

**Vhoďte SVG** — nasměrujte adresář na cesty ikon a každé SVG se stane ikonou
pojmenovanou podle názvu souboru:

```php
// config/wire-core.php
'icons' => [
    'paths' => [
        resource_path('icons'), // resource_path('icons/cart.svg') => 'cart'
    ],
],
```

**Přidejte sadu ikon** — zaregistrujte třídu sady pod prefixem; její ikony se
pak používají jako `prefix:name` a vykreslí se správně, i když jsou stroke-based /
nejsou 20×20 (Lucide, Feather, Heroicons outline):

```php
'icons' => [
    'sets' => [
        'default' => NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class, // "pencil"
        'lucide'  => App\Wire\Icons\LucideIconSet::class,                      // "lucide:home"
    ],
],
```

**Vyměňte výchozí styl** — nasměrujte `default_set` na klíč jiné sady, aby se
stala neprefixovaným základem:

```php
'icons' => [
    'default_set' => 'lucide',  // holé názvy se resolvují vůči Lucide; "default:pencil" stále funguje
    'sets' => [
        'lucide'  => App\Wire\Icons\LucideIconSet::class,
        'default' => NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class,
    ],
],
```

Přibalený `DefaultIconSet` je kompletní sada Heroicons solid. Kompletní API,
model `prefix:name`, vlastní sady a přístupnost viz
[Core → Foundation → Ikony](../core/foundation/icons.md#ikony).

---

<a id="per-component-tweaks"></a>
## Úpravy per-komponenta

Pro jedno pole, sloupec nebo akci upřednostněte fluent API před přepisem
pohledu. Každé pole podporuje libovolné HTML atributy a extra třídy:

```php
TextInput::make('sku')
    ->extraAttributes(['class' => 'font-mono tracking-wide', 'data-test' => 'sku'])
    ->size('lg');
```

`extraAttributes()` se sloučí na vnější element komponenty, takže můžete přidat
utility třídy, `data-*` hooky nebo ARIA atributy bez zásahu do markupu. Je na
každé komponentě — polích, infolist entries, display komponentách i widgetech —
a bere closure stejně ochotně jako pole. Když potřebujete opravdu jiný markup,
postavte [vlastní pole](../forms/custom-fields.md) nebo
[ViewField](../forms/fields/view-field.md).

---

<a id="overriding-views"></a>
## Přepis pohledů

Když je úprava strukturální — jiný layout, prvky navíc, přepracovaná buňka —
publikujte pohledy balíčku a upravte Blade. Publikované pohledy mají přednost
před kopiemi v balíčku.

```bash
php artisan vendor:publish --tag=wire-core::views
php artisan vendor:publish --tag=wire-forms::views
php artisan vendor:publish --tag=wire-table::views
php artisan vendor:publish --tag=wire-sortable::views
```

Každý příkaz zkopíruje Blade soubory daného balíčku do
`resources/views/vendor/{package}/` — například
`resources/views/vendor/wire-forms/components/text-input.blade.php`. Upravte
kopii; smažte ji pro návrat k výchozímu stavu balíčku.

> **Publikujte jen to, co měníte.** Každý přepsaný pohled je soubor, který nyní
> udržujete napříč upgrady. Pro jednorázový markup je vlastní pole nebo `ViewField`
> méně náročné na údržbu než přepis sdíleného pohledu. Přepsané pohledy znovu
> zkontrolujte při [upgradu](upgrade.md).

Sdílený chrome pole (label, hint, marker povinnosti, helper text, chyba) žije
v `partials/field-wrapper-start.blade.php` a `field-wrapper-end.blade.php`;
jejich přepisem přestylujete wrapper všech polí najednou.

---

## Lokalizace

Všechny řetězce směřující k uživateli pocházejí z publikovatelných překladových
souborů. Balíček dodává angličtinu (`en`) a češtinu (`cs`).

```bash
php artisan vendor:publish --tag=wire-core::translations
php artisan vendor:publish --tag=wire-forms::translations
php artisan vendor:publish --tag=wire-table::translations
php artisan vendor:publish --tag=wire-sortable::translations
```

Soubory přistanou v `lang/vendor/{package}/{locale}/`. Upravte publikovaný soubor
pro změnu formulací, nebo přidejte nový adresář locale pro překlad. Formáty data
a času pro pole formulářů se konfigurují samostatně v `config/wire-forms.php`
(`date_format`, `time_format`, `datetime_format`, `first_day_of_week`).

---

## Viz také

- [Začínáme](getting-started.md) — Tailwind cesty a barva primary
- [Konfigurace](configuration.md) — veškerá publikovatelná konfigurace
- [Rozšíření formulářů](../forms/custom-fields.md) — vlastní pole, když se markup musí lišit
- [Upgrade](upgrade.md) — opětovná kontrola přepisů po aktualizaci

---
order: 60
---

# Vzhled a přizpůsobení

Wire dodává nestylovaný-ale-rozumný Tailwind markup s plnými dark-mode variantami.
Vzhled si přizpůsobíte na čtyřech úrovních, od nejlehčí po nejtěžší:

| Úroveň | Dosah | Náročnost |
|-------|-------|--------|
| [Barvy](#colors) | Akcentová a neutrální paleta všude | Tailwind config |
| [Ikony](#icons) | Výměna nebo přidání ikon globálně | `wire-core` config |
| [Per-komponenta](#per-component-tweaks) | Jedno pole/sloupec/akce | Fluent API |
| [Přepis pohledů](#overriding-views) | Markup libovolné komponenty | Publish + editace Blade |

---

<a id="colors"></a>
## Barvy

Komponenty Wire stojí na dvou Tailwind škálách barev: **`primary`** (akcent —
tlačítka, focus ringy, aktivní stavy) a **`gray`** (plochy, ohraničení, text).
`primary` je **povinná** — bez ní se interaktivní prvky vykreslí neviditelně.

Definujte ji v konfiguraci Tailwindu podle
[Začínáme → Barva primary](getting-started.md#primary-color). Stručně:

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
[Core → Foundation → Ikony](core/foundation.md#icons).

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

`extraAttributes()` se sloučí na vykreslený ovládací prvek, takže můžete přidat
utility třídy, `data-*` hooky nebo ARIA atributy bez zásahu do markupu. Když
potřebujete opravdu jiný markup, postavte [vlastní pole](forms/custom-fields.md)
nebo [ViewField](forms/fields/view-field.md).

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
- [Rozšíření formulářů](forms/custom-fields.md) — vlastní pole, když se markup musí lišit
- [Upgrade](upgrade.md) — opětovná kontrola přepisů po aktualizaci

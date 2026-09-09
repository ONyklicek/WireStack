---
order: 10
summary: Volitelný admin shell — co instalace zapíše, co shell čte a jeden řádek pro Tailwind, bez kterého se sidebar vykreslí jako nic.
---

# Admin shell

Všechno, co admin potřebuje, ve frameworku už bylo — kromě rámu kolem toho: co je
zaregistrované ([`Catalog`](../panels/navigation.md#catalog-api)), jak se to seskupuje a
řadí ([`Workspace`](../panels/navigation.md)), kde žije stránka každého klíče
(`ResolvesPageUrls`), samotné stránky, command palette, toasty. Co žádný balíček
nevezl, byl layout se sidebarem — takže tuhle část psala každá aplikace znovu.

`wire-admin` je přesně tahle část a nic víc.

## Instalace

```bash
composer require nyoncode/wire-admin
php artisan wire-admin:install
```

Instalátor udělá tři věci, které `composer require` udělat nemůže, a u každé řekne, jak dopadla:

| Krok | Co zapíše |
| --- | --- |
| Publikuje `App\Providers\WireAdminServiceProvider` | Ten jeden řádek, který jmenuje layout, `config('livewire.component_layout')` |
| Zapíše `resources/views/components/layouts/admin.blade.php` | Váš layout, který jmenuje komponentu shellu a plní její sloty |
| Zaregistruje provider v `bootstrap/providers.php` | Aby řádek výš vůbec běžel |
| Přidá jeden řádek `@source` do `resources/css/app.css` | Aby Tailwind zkompiloval třídy, které views shellu používají |
| Publikuje překlady | Těch pár řetězců, které sidebar ukazuje |

Nic se nepřepisuje, takže druhý běh je bezpečný: layout, který jste upravili, zůstane a nahlásí se jako už existující. Aplikace, která si providery drží jinde - Laravel 10 nebo vlastní konvence - dostane řádek k doplnění místo tichého úspěchu a zbytek instalace doběhne.

### Jeden řádek, který Tailwind potřebuje

Třetí řádek tabulky je krok, který nikoho nenapadne a narazí na něj každý. Tailwind 4 hledá třídy skenováním zdrojů a přeskakuje to, co přeskakuje `.gitignore` - tedy `vendor/`, kde leží každý view z `wire-admin`, `wire-panels` i z modulů. Bez toho řádku se ty třídy nikdy nezkompilují: **sidebar se vykreslí bez šířky, bez barvy a bez odsazení a nikde se neobjeví žádná chyba.** Markup je správně a stylesheet o tom mlčí.

Instalátor ho zapíše za vás a míří na celý vendor adresář místo na jeden balíček, takže modul, který doinstalujete zítra, je pokrytý bez další úpravy:

```css
@import "tailwindcss"; /* [tl! focus:2] */
@source "../../vendor/nyoncode";
```

### Když si píšete vlastní layout

Layout shellu nese vedle markupu ještě dvě věci a vlastní layout je musí nést
také:

```blade
@include('wire-core::partials.density')   {{-- [tl! focus:1] --}}
@include('wire-core::partials.shape')
```

Jsou to pravidla za [Vzhled → Hustota](../start/theming.md#hustota) a
[Tvar](../start/theming.md#tvar). Bez nich nastavení pořád vyrenderuje svůj
atribut na `<html>` a nikdo podle něj nic neudělá — stránka, která tvrdí, že je
kompaktní, a není. Je to ta samá tichá podoba jako chybějící řádek `@source`
výše.

Samotné atributy pocházejí taky z layoutu:

```blade
<html data-density="{{ \NyonCode\WireCore\Foundation\Enums\Density::configured()->value }}"
      data-shape="{{ \NyonCode\WireCore\Foundation\Enums\Shape::configured()->value }}">
```

Pokud váš stylesheet není `resources/css/app.css` nebo Tailwind neimportuje, instalátor to řekne a řádek vám dá místo toho, aby ho zapsal do souboru, který Tailwind nikdy nečte. Doplňte ho pod import a cestu upravte podle toho, kde stylesheet leží.

Pak stránky zaroutujte a vykreslí se uvnitř shellu:

```php
// routes/web.php
Route::middleware(['web', 'auth'])->prefix('admin')->group(fn () => Route::wireResources());
```

**Instalace balíčku pořád není jeho přijetí.** Žádný provider uvnitř `wire-admin` nenastavuje `livewire.component_layout` - spuštění instalátoru je to, čím si o něj aplikace řekne, a to je něco jiného než mít balíček na disku. Když příkaz přeskočíte, komponenty máte dál a váš vlastní rám může použít samotný sidebar.

## Jak to funguje

**Je to samostatný balíček a právě to je ten opt-in.** Nic ho nevyžaduje; on
vyžaduje `wire-panels` a všechno pod ním. Aplikace, která si nainstaluje
`wire-panels` kvůli stránkám a routovacímu makru, dostane přesně to, bez chrome,
které by musela vypínat — protože přepínač je `composer require`.

**Instalace ještě není přijetí.** Žádný provider nenastavuje
`livewire.component_layout`. Shell je Blade komponenta, takže se stránka vykreslí
uvnitř něj, až když to řekne váš vlastní layout — což zároveň umožňuje nechat si
svůj rám a použít jen sidebar.

**Čte seamy, které už existovaly, a nepřidává žádný stav.** Menu pochází
z `Workspace`, každý odkaz z `ResolvesPageUrls` (které neodpovídá vůbec nic,
dokud routování někdo nevlastní) a aktivní položka z názvu aktuální routy. Není
tu žádný registr, žádné URL schéma ani objekt `Panel`.

**Zóna i aktivní položka se čtou jednou, při renderu stránky.** Ne při každém
renderu: během Livewire updatu je `Route::currentRouteName()` rovno
`livewire.update`, takže cokoli z něj odvozeného by bylo správně při prvním
vykreslení a špatně navždycky potom — a vypadalo by to bezvadně. Layout se
vykresluje jen při plném načtení stránky, a to je důvod, proč je to čtení
správné, ne šťastné.

## Základní použití

Napište layout, který vaše stránky jmenují, a naplňte jeho sloty:

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:head>                                    {{-- [tl! focus:start] --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </x-slot:head>

    <x-slot:brand>{{ config('app.name') }}</x-slot:brand>

    <x-slot:user>
        <x-app-user-menu />
    </x-slot:user>                                   {{-- [tl! focus:end] --}}

    {{ $slot }}
</x-wire-admin::layout>
```

Pak ho jednou pojmenujte tam, kde aplikace konfiguruje Livewire:

```php
// V boot() service provideru, nebo v config/livewire.php.
// Klíč je `component_layout`, ne `layout` — Livewire 4 čte první z nich
// a druhý selže jako „No hint path defined for [layouts]".
config()->set('livewire.component_layout', 'components.layouts.admin');
```

Každá full-page komponenta — včetně stránek, které registruje
`Route::wireResources()` — se teď vykresluje uvnitř shellu, s menu vedle sebe.

## Co nedělá

| Tohle ne | Protože |
| --- | --- |
| Objekt `Panel` s fluent API | Riziko, které pojmenovala ADR 0020. Třída držící konfiguraci shellu je přesně to, co by registry pod ním nakonec musely znát |
| Vlastní URL schéma | Zóna je `name()` route skupiny; URL vlastní router |
| Vlastní cestu registrace | Vykresluje to, co už `Catalog` drží, a neučí se, co která položka je za typ |
| Konfiguraci auth, tenancy nebo brandingu | Sloty a vaše vlastní middleware |

## V této sekci

| Stránka | Co pokrývá |
| --- | --- |
| [Layout](layout.md) | Sloty, přihlašovací rám a roh s přihlášeným uživatelem |
| [Sidebar](sidebar.md) | Komponenta menu, sbalená lišta a jak se k ní dostane zóna |
| [Branding a motiv](branding.md) | Logo, značka, třístavový přepínač motivu a publikování views |

## Související

- [Panely](../panels/overview.md) — vrstva, jejíž stránky se v tomhle shellu vykreslují
- [Navigace](../panels/navigation.md) — z čeho je menu složené
- [Moduly](../panels/modules.md) — oblasti, kterými do něj přispívá nainstalovaný balíček
- [Globální vyhledávání](../core/global-search.md) — palette, kterou rám mountuje

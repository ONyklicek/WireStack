---
order: 92
summary: Volitelný admin shell — layout a sidebar nad vším, co už je zaregistrované, dodávaný jako vlastní balíček, takže jediný způsob, jak ho získat, je nainstalovat ho.
---

# Admin shell

Všechno, co administrace potřebuje, ve frameworku už bylo — kromě rámu okolo:
co je zaregistrované ([`Catalog`](resources.md#catalog-api)), jak se to seskupuje
a řadí ([`Workspace`](resources.md#navigace-a-workspace)), kde leží stránka
každého klíče (`ResolvesPageUrls`), samotné stránky, command paleta, toasty.
Co nedodával žádný balíček, byl layout se sidebarem, takže tuhle část psala
každá aplikace znovu.

`wire-admin` je právě ta část a nic jiného.

```bash
composer require nyoncode/wire-admin
```

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

## Sloty

| Slot | Kam se dostane | Typický obsah |
| --- | --- | --- |
| `head` | do `<head>`, před vlastní tagy Livewire | `@vite(...)`, meta tagy, font |
| `brand` | vlevo v horní liště | název aplikace, logo |
| `topbar` | horní lišta, za spouštěčem palety | přepínač tenantů, drobečky |
| `user` | konec horní lišty | účet, odhlášení |
| *(výchozí)* | element `<main>` | stránka |

Sloty místo konfigurace jsou záměr: třída, která drží brand, barvy a auth, je
přesně to, co z shellu udělá panel builder — a všechno, co by nesla, je markup,
který umíte napsat sami.

## Sidebar samotný

Aplikace s vlastním rámem použije menu bez layoutu:

```blade
<x-wire-admin::sidebar />
<x-wire-admin::sidebar :linked-only="true" />
```

`linkedOnly` rozhoduje, co se stane se zaregistrovanou položkou, kterou tahle
zóna neroutuje. Ve výchozím stavu zůstane v menu jako řádek bez odkazu —
viditelná, neklikatelná — což je poctivý obraz částečně zaroutované aplikace.
S `linkedOnly` se místo toho vynechá.

Obě formy přijmou explicitní `zone` a `activeKey`, když je hostitel už vyřešil:

```blade
<x-wire-admin::sidebar :zone="$zone" :active-key="$activeKey" />
```

## Zóny

[Zóna](resources.md#zony) je name prefix route skupiny a shell k jejímu
respektování nepotřebuje nic deklarovat: stejné markup odkazuje do `admin` na
admin stránce a do `business` na business stránce, protože zóna se bere z routy,
která se právě vykresluje. Paleta dělá totéž — a proto její spouštěč sedí v rámu,
ne na stránce.

## Změna vzhledu

```bash
php artisan vendor:publish --tag=wire-admin::views
```

Publikované views jsou obyčejný Blade. Je to záměrně hrubší než konfigurační
API — a právě ta hrubost drží shell od toho, aby se z něj nabalováním stal panel
framework.

## Co nedělá

| Tohle ne | Protože |
| --- | --- |
| Objekt `Panel` s fluent API | Riziko, které pojmenovala ADR 0020. Třída držící konfiguraci shellu je přesně to, co by registry pod ním nakonec musely znát |
| Vlastní URL schéma | Zóna je `name()` route skupiny; URL vlastní router |
| Vlastní cestu registrace | Vykresluje to, co už `Catalog` drží, a neučí se, co která položka je za typ |
| Konfiguraci auth, tenancy nebo brandingu | Sloty a vaše vlastní middleware |

## Související

- [Resources](resources.md) — z čeho je menu složené a jak fungují zóny
- [Doménové moduly](modules.md) — oblasti, kterými do něj přispívá nainstalovaný balíček
- [Globální hledání](global-search.md) — paleta, kterou rám mountuje

---
order: 1
summary: Rozcestník dokumentace Wire — dva povrchy, kvůli kterým sem většina lidí přichází, z čeho jsou postavené a vrstvy, které z nich složí celý admin.
---

# Dokumentace Wire

Wire je ekosystém Livewire komponent pro Laravel: tabulka, formulářový systém a
vrstvy, které z nich udělají admin — vlastník pro každou entitu, shell, ve kterém
se vykreslí, a hotové oblasti, které se instalují místo psaní.

```bash
composer require nyoncode/wire-suite
php artisan wire:install
```

Nic tě k téhle cestě nenutí. Každý balíček níž se instaluje samostatně a celý
stack nad `wire-core` je volitelný — viz [Instalace Wire](start/installation.md)
pro setup na jeden příkaz a [Mapa projektu](start/project-map.md) pro to, co na
čem závisí.

## Začínáme

| Dokument | Popis |
|----------|-------|
| [Instalace Wire](start/installation.md) | Celý stack v jednom require a jeden interaktivní příkaz |
| [Začínáme](start/getting-started.md) | Tailwind, Livewire, assety a první tabulka a formulář |
| [Konfigurace](start/configuration.md) | Publikované config soubory, proměnné prostředí a výchozí hodnoty |
| [Autorizace](start/authorization.md) | Gates, policies, oprávnění, pravidla tabulek a formulářů |
| [Motivy a přizpůsobení](start/theming.md) | Barvy, ikony, přebíjení views a lokalizace |
| [Testování](start/testing.md) | Standalone, Livewire a unit testy formulářů a tabulek |
| [Kuchařka](start/cookbook.md) | Receptury podle úkolů, postavené na veřejném API |
| [Ošetření chyb](start/error-handling.md) | Výjimky, které který balíček vyhazuje, a co znamenají |
| [Řešení potíží](start/troubleshooting.md) | Opravy běžných konfiguračních problémů |
| [Průvodce upgradem](start/upgrade.md) | Verzování, požadavky a co změnila 2.0 |
| [Mapa projektu](start/project-map.md) | Přehled balíčků, instalační cesty, rozvržení zdrojů, testovací příkazy |

## Formuláře

`wire-forms` — schéma polí deklarované v PHP, navázané na Livewire hostitele nebo
použité samostatně.

| Dokument | Popis |
|----------|-------|
| [Přehled formulářů](forms/overview.md) | Jeden formulář, více formulářů, standalone použití a průběh ukládání |
| [Validace](forms/validation.md) | Pravidla, hlášky a vlastní validační chování |
| [Reaktivní pole](forms/reactive-fields.md) | Pole, která živě reagují na jiná pole |
| [Save lifecycle](forms/save-lifecycle.md) | Validace, mutace, perzistence a notifikace |
| [Reference polí](forms/fields/index.md) | Všechna pole — vstupní, layoutová, zobrazovací, relační, repeater |
| [Rozšiřování formulářů](forms/custom-fields.md) | Vlastní pole, zobrazovací komponenty, presety a balíčkování |

## Tabulka

`wire-table` — povrch pro výpisy: sloupce, filtry, akce, inline editace, exporty a
vrstva gest.

| Dokument | Popis |
|----------|-------|
| [Přehled tabulky](table/overview.md) | První tabulka, `WithTable` a základní konfigurace |
| [Sloupce](table/columns/index.md) | Typy sloupců, formátování, hledání, řazení, responzivní viditelnost |
| [Filtry](table/filters/index.md) | Vestavěné filtry a vlastní chování dotazu |
| [Akce](table/actions.md) | Řádkové, hromadné a hlavičkové akce s modálními formuláři |
| [Akce nad záznamem](table/record-actions.md) | Kontextové menu řádku a co všechno umí |
| [Výběr](table/selection.md) a [Gesta](table/gestures.md) | Klávesová navigace, rozsahy, tažení výběru, fill handle |
| [Exporty](table/exports.md) a [Importy](table/imports.md) | CSV, Excel a PDF ven; namapované, přetypované a zvalidované CSV dovnitř |
| [Souhrny](table/summaries.md) | Agregace v patičce, scopes, rollupy a celkové součty |
| [Seskupení řádků](table/grouping.md) a [Podřádky](table/sub-rows.md) | Skupiny s mezisoučty; podřízené záznamy uvnitř řádku |
| [Relation managery](table/relation-managers.md) | Relací omezené tabulky jako samostatné komponenty |
| [Zdroje dat](table/data-sources.md) | Tabulka nad něčím, co není Eloquent |
| [Pokročilé funkce](table/advanced.md) | Polling, výkon a ladění |

## Core

`wire-core` — z čeho jsou oba povrchy postavené a povrchy, které vlastní seznam
ani formulář nemají.

| Dokument | Popis |
|----------|-------|
| [Wire Core](core/overview.md) | Co žije v core, jak jsou jeho moduly navrstvené a která stránka na co odpovídá |
| [Foundation](core/foundation/index.md) | Sdílené traity, ikony, barvy, enumy a Blade helpery |
| [Akce](core/actions/index.md) | Řádkové, hromadné a hlavičkové akce, skupiny, modály, wizardy, fronty |
| [Modály](core/modals.md) | Potvrzovací, slide-over a wizard komponenty |
| [Notifikace](core/notifications/index.md) | Hodnotové objekty notifikací, manager a drivery |
| [Widgety](core/widgets/index.md) | Statistiky, grafy, tabulkové a vlastní widgety, dashboardy |
| [Infolisty](core/infolists/index.md) | Read-only zobrazení jednoho záznamu podle schématu |
| [Editovatelné panely](core/record-panels.md) | Infolist, který jde editovat — každá změna se uloží sama |
| [Schéma](core/schema/overview.md) | Sdílený layoutový slovník — Grid, Section, Flex, Tabs, Wizard |
| [Globální vyhledávání](core/global-search.md) | Jedna command palette nad vším zaregistrovaným |
| [Auditní log](core/audit.md) | Zápis změn modelů a událostí kolem tabulek |
| [Pluginy](core/plugins/index.md) | Rozšiřovací body pro aplikace i balíčky, hooky a registry typů |

## Panely

`wire-panels` — vlastnická vrstva: jedna entita deklarovaná jednou a k ní
stránky, menu a routy.

| Dokument | Popis |
|----------|-------|
| [Přehled panelů](panels/overview.md) | Co je vlastnická vrstva a co se s ní instaluje |
| [Resources](panels/resources.md) | Identita, kontrakty povrchů, pojmenování, registrace |
| [Stránky](panels/pages.md) | Seznam, založení, editace, detail a dashboard |
| [Navigace](panels/navigation.md) | Položky, skupiny, workspace a katalog |
| [Routování](panels/routing.md) | Deklarované stránky jako URL, middleware per resource, zóny |
| [Moduly](panels/modules.md) | Resource a dashboardy jedné byznysové oblasti, deklarované jednou |

## Admin

`wire-admin` — volitelný shell. Všechno ostatní funguje i bez něj.

| Dokument | Popis |
|----------|-------|
| [Admin shell](admin/overview.md) | Instalace, co shell čte, a jeden řádek pro Tailwind |
| [Layout](admin/layout.md) | Sloty, přihlašovací rám a roh s přihlášeným uživatelem |
| [Sidebar](admin/sidebar.md) | Komponenta menu a 64pixelová lišta |
| [Branding a motiv](admin/branding.md) | Logo, třístavový přepínač motivu, publikování views |

## Moduly

Celé oblasti dodané jako composer balíčky.

| Dokument | Popis |
|----------|-------|
| [Hotové moduly](modules/index.md) | Co která nainstaluje a jak ji zase odebrat |
| [Uživatelé](modules/users.md) | Uživatelská oblast, s rolemi tam, kde je aplikace má |
| [Týmy a dvoufaktor](modules/teams-and-two-factor.md) | Zapnutí dvoufaktoru přes Fortify a rolí per tým |
| [Přihlašování](modules/auth.md) | Přihlášení, reset hesla, ověření, dvoufaktorová výzva |
| [Nastavení](modules/settings.md) | Typovaná nastavení aplikace, s obrazovkou i úložištěm |
| [Notifikace](modules/notifications.md) | Historie za zvonečkem |
| [Audit](modules/audit.md) | Obrazovka pro stopu, kterou wire-core už zapisuje |
| [Média](modules/media.md) | Uploady, procházitelná knihovna, náhledy a picker do formulářů |

## Sortable

| Dokument | Popis |
|----------|-------|
| [Přehled sortable](sortable/overview.md) | Drag & drop řazení řádků a sloupců |
| [Instalace](sortable/installation.md) | Nastavení balíčku a požadavky na frontend |
| [Řazení řádků](sortable/row-sorting.md) a [řazení sloupců](sortable/column-sorting.md) | Dvě osy a co si která ukládá |
| [Přizpůsobení](sortable/customization.md) a [pokročilé](sortable/advanced.md) | Úchyty, omezení a rozšiřovací body |
| [API reference](sortable/api-reference.md) | API sortable tabulky a traity |

## Boost

AI nástroje pro ekosystém — MCP server, guidelines a skills pro kódovací agenty.

| Dokument | Popis |
|----------|-------|
| [Přehled Boostu](boost/overview.md) | Co je Wire Boost a jak pomáhá AI agentům |
| [Instalace](boost/installation.md) | Instalace balíčku a nastavení agentů |
| [MCP server a nástroje](boost/mcp-tools.md) | MCP server a jeho introspekční nástroje |
| [Guidelines a skills](boost/guidelines-and-skills.md) | Vždy načtená a on-demand vrstva AI kontextu |

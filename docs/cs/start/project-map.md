---
order: 100
summary: Čtrnáct balíčků, k čemu který je, na čem závisí a nejkratší instalace toho, co opravdu chcete.
---

# Mapa projektu

Wire je ekosystém Livewire balíčků rozdělený do čtrnácti composer balíčků.
Nainstalujte si jen ten, který odpovídá tomu, co stavíte; jeho závislosti se
dotáhnou samy a nic nad ním se vám neinstaluje.

## Balíčky

| Balíček | Composer název | K čemu je | Závisí na |
|---------|----------------|-----------|-----------|
| Core | `nyoncode/wire-core` | Sdílené akce, modaly, notifikace, widgety, infolisty, schéma, auditní log, Blade helpery | Laravel, Livewire |
| Forms | `nyoncode/wire-forms` | Schéma formuláře, komponenty polí, validace, životní cyklus ukládání | Core |
| Table | `nyoncode/wire-table` | Tabulky, sloupce, filtry, akce, inline editace, exporty, gesta | Core, Forms |
| Sortable | `nyoncode/wire-sortable` | Drag & drop řazení řádků a sloupců | Core, Table |
| Panels | `nyoncode/wire-panels` | Vlastnická vrstva: resource, jejich stránky a routovací makro | Core, Forms, Table |
| Admin | `nyoncode/wire-admin` | Volitelný shell: layout a sidebar nad katalogem | Core, Panels |
| Suite | `nyoncode/wire-suite` | Celý stack v jednom require plus `php artisan wire:install` | všechno výše |
| Users | `nyoncode/wire-module-users` | Hotová uživatelská oblast, s rolemi tam, kde je aplikace má | Panels |
| Auth | `nyoncode/wire-module-auth` | Odhlášené obrazovky nad Laravel Fortify | Core |
| Settings | `nyoncode/wire-module-settings` | Typovaná nastavení aplikace, s úložištěm i obrazovkou | Panels |
| Notifications | `nyoncode/wire-module-notifications` | Uložená historie za zvonečkem | Panels |
| Audit | `nyoncode/wire-module-audit` | Obrazovka pro stopu, kterou core už zapisuje | Panels |
| Media | `nyoncode/wire-module-media` | Knihovna médií: uploady, složky, náhledy, picker do formulářů | Panels |
| Boost | `nyoncode/wire-boost` | AI nástroje: MCP server, guidelines a skills pro agenty | Core |

Graf má jeden směr. `wire-panels` smí jmenovat každý komponentový balíček a žádný
z nich nesmí jmenovat jeho; `wire-admin` sedí nad panely a nic ho nevyžaduje;
modul sedí nad obojím. Právě to dělá každou vrstvu odebratelnou — proč je ten směr
záměrem, vysvětlují [Panely](../panels/overview.md).

## Instalační cesty

| Cíl | Instalace |
|-----|-----------|
| Celý admin, nastavený interaktivně | `composer require nyoncode/wire-suite` a pak `php artisan wire:install` |
| Postavit tabulkové UI | `composer require nyoncode/wire-table` |
| Jen samostatné formuláře | `composer require nyoncode/wire-forms` |
| Přidat řazení řádků nebo sloupců | `composer require nyoncode/wire-sortable` |
| Deklarovat resource a jejich stránky | `composer require nyoncode/wire-panels` |
| Přidat layout a sidebar | `composer require nyoncode/wire-admin` |
| Jen sdílené widgety/akce | `composer require nyoncode/wire-core` |
| Přidat hotovou oblast | `composer require nyoncode/wire-module-users` (a dalších pět) |
| Přidat nástroje pro AI agenty (MCP) | `composer require nyoncode/wire-boost --dev` |

## Mapa dokumentace

| Oblast | Začněte tady | Hlavní reference |
|--------|------------|------------------|
| Setup | [Instalace Wire](installation.md) | [Začínáme](getting-started.md), [Konfigurace](configuration.md), [Autorizace](authorization.md) |
| Formuláře | [Přehled formulářů](../forms/overview.md) | [Reference polí](../forms/fields/index.md), [Validace](../forms/validation.md), [Životní cyklus ukládání](../forms/save-lifecycle.md) |
| Tabulky | [Přehled tabulky](../table/overview.md) | [Sloupce](../table/columns/index.md), [Filtry](../table/filters/index.md), [Akce](../table/actions.md), [Exporty](../table/exports.md) |
| Core UI | [Foundation](../core/foundation/index.md) | [Akce](../core/actions/index.md), [Schéma](../core/schema/overview.md), [Modaly](../core/modals.md), [Notifikace](../core/notifications/index.md), [Widgety](../core/widgets/index.md), [Infolisty](../core/infolists/index.md), [Pluginy](../core/plugins/index.md) |
| Vlastnická vrstva | [Přehled panelů](../panels/overview.md) | [Resources](../panels/resources.md), [Stránky](../panels/pages.md), [Navigace](../panels/navigation.md), [Routování](../panels/routing.md) |
| Admin shell | [Admin shell](../admin/overview.md) | [Layout](../admin/layout.md), [Sidebar](../admin/sidebar.md), [Branding](../admin/branding.md) |
| Hotové oblasti | [Hotové moduly](../modules/index.md) | [Uživatelé](../modules/users.md), [Přihlašování](../modules/auth.md), [Nastavení](../modules/settings.md), [Média](../modules/media.md) |
| Sortable | [Přehled sortable](../sortable/overview.md) | [Instalace](../sortable/installation.md), [Řazení řádků](../sortable/row-sorting.md), [Řazení sloupců](../sortable/column-sorting.md) |
| Boost (AI) | [Přehled Boostu](../boost/overview.md) | [Instalace](../boost/installation.md), [MCP server a nástroje](../boost/mcp-tools.md), [Guidelines a skills](../boost/guidelines-and-skills.md) |

## Rozložení zdrojů

| Cesta | Obsah |
|-------|-------|
| `packages/core/src/Actions` | Action, BulkAction, HeaderAction, presety, helpery modálních akcí |
| `packages/core/src/Foundation/Schema` | Sdílený layoutový slovník — Grid, Section, Fieldset, Flex, Tabs/Tab, Wizard/Step, Callout, EmptyState |
| `packages/core/src/Foundation/View` | Samostatné `<x-wire::*>` Blade komponenty zrcadlící layouty schématu |
| `packages/core/src/Foundation/Support` | Sdílené helpery — `ResponsiveGrid` (sloupce podle breakpointů), `MobileSheet`, `EnumResolver` |
| `packages/core/src/Foundation/Concerns` | Kanonické sdílené traity — `HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasActions`, `HasSheetOnMobile`, … |
| `packages/core/src/Foundation/Registration` | `Catalog` — všechno, co aplikace zaregistrovala, ať je to cokoli — plus kontrakty `RegistrySource` / `HasRegistryKey`, kterými se k němu registr připojí |
| `packages/core/src/Foundation/Routing` | Co nese deklarace stránky (`ProvidesPages`, `RoutePage`, `ConfiguresRoutes`), `Zone` a švy `ResolvesPageUrls` / `RegistersPageRoutes`, na které odpovídá URL konvence |
| `packages/core/src/GlobalSearch` | ⌘K palette, její vyhledávací služba a hodnotový objekt výsledku |
| `packages/core/src/Core/Resources` | Identita resourcu, registr, `Workspace` a navigační slovník |
| `packages/core/src/Modals` | Třídy modalů, potvrzení, slide-overu a wizardu |
| `packages/core/src/Notifications` | Hodnotový objekt notifikace, manager, drivery |
| `packages/core/src/Widgets` | Statistiky, grafy, tabulkové a vlastní widgety a registr dashboardů |
| `packages/core/src/Infolists` | Infolist, entries, read-only zobrazení záznamu |
| `packages/core/src/Panels` | Editovatelné panely záznamu — infolist, jehož entries zapisují zpět |
| `packages/core/src/Audit` | Auditní záznamy, události, logger, modelový trait, akce s auditní stopou |
| `packages/core/src/Core/Plugin` | Kontrakt pluginu, manager, hooky, registry typů |
| `packages/forms/src/Components` | Formulářová pole, layoutové komponenty, relační pole, repeater |
| `packages/forms/src/Forms` | Veřejné API `Form` a Livewire trait `WithForms` |
| `packages/table/src/Columns` | Třídy tabulkových sloupců a sloupce s inline editací |
| `packages/table/src/Filters` | Select, datumové, číselné rozsahy, ternární a vlastní filtry |
| `packages/table/src/Export` | Podpora CSV, Excel a PDF exportů |
| `packages/table/src/Concerns/WithTable.php` | Livewire integrace pro stav a akce tabulky |
| `packages/sortable/src` | Helpery sortable tabulky, Livewire trait, model pořadí sloupců |
| `packages/panels/src/Resources/Pages` | `ListPage`, `CreatePage`, `EditPage`, `ViewPage`, `DashboardPage` |
| `packages/panels/src/Routing` | `ResourceRoutes`, skupina rout deklarovaná v configu a URL registrovaných stránek |
| `packages/admin/src/View` | Komponenty shellu — layout, auth layout, sidebar, položka menu |
| `packages/admin/src/Install` | `wire-admin:install` a co zapisuje |
| `packages/suite/src/Install` | `wire:install` — interaktivní setup nad každým nainstalovaným balíčkem |
| `packages/module-*/src` | Vždy jedna hotová oblast: její manifest modulu, resource, stránky a podpora |
| `packages/boost/src/Mcp` | MCP server a jeho introspekční nástroje |

## Testovací příkazy

```bash
composer test              # všechno
composer test:core
composer test:forms
composer test:table
composer test:panels
composer test:admin
composer test:sortable
composer test:suite
composer test:boost
composer test:module-users
composer test:module-auth
composer test:module-settings
composer test:module-notifications
composer test:module-audit
composer test:module-media

composer lint
composer analyse
```

## Související

- [Instalace Wire](installation.md) — setup na jeden příkaz nad čistým Laravelem
- [Konfigurace](configuration.md) — každý config soubor, který tyhle balíčky publikují
- [Průvodce upgradem](upgrade.md) — verzování a co změnila 2.0

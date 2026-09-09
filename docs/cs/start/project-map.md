---
order: 30
---

# Mapa projektu

Wire je ekosystém balíčků pro Laravel Livewire rozdělený do čtyř Composer balíčků. Nainstalujte pouze ten, který odpovídá UI, jež potřebujete; závislosti se dotáhnou automaticky.

## Balíčky

| Balíček | Composer název | Účel | Závisí na |
|---------|---------------|---------|------------|
| Core | `nyoncode/wire-core` | Sdílené akce, modály, notifikace, widgety, audit log, Blade helpery | Laravel, Livewire |
| Forms | `nyoncode/wire-forms` | Samostatné schéma formulářů, komponenty polí, validace, životní cyklus ukládání | Core |
| Table | `nyoncode/wire-table` | Tabulky, sloupce, filtry, akce, inline editace, exporty | Core, Forms |
| Sortable | `nyoncode/wire-sortable` | Drag and drop řazení řádků a sloupců pro Wire Table | Core, Table |
| Boost | `nyoncode/wire-boost` | AI nástroje: MCP server, guidelines a skills pro kódovací agenty | Core, Laravel MCP |

## Instalační cesty

| Cíl | Instalace |
|------|---------|
| Postavit UI tabulky | `composer require nyoncode/wire-table` |
| Postavit jen samostatné formuláře | `composer require nyoncode/wire-forms` |
| Přidat řazení řádků nebo sloupců | `composer require nyoncode/wire-sortable` |
| Použít jen sdílené widgety/akce | `composer require nyoncode/wire-core` |
| Přidat nástroje pro AI agenty (MCP) | `composer require nyoncode/wire-boost --dev` |

## Mapa dokumentace

| Oblast | Začněte zde | Hlavní reference |
|------|------------|-----------------|
| Nastavení | [Začínáme](getting-started.md) | [Konfigurace](configuration.md), [Autorizace](authorization.md) |
| Formuláře | [Přehled formulářů](forms/overview.md) | [Reference polí](forms/fields/index.md), [Validace](forms/validation.md), [Životní cyklus ukládání](forms/save-lifecycle.md) |
| Tabulky | [Přehled tabulek](table/overview.md) | [Sloupce](table/columns/index.md), [Filtry](table/filters/index.md), [Akce](table/actions.md), [Exporty](table/exports.md) |
| Sortable | [Přehled sortable](sortable/overview.md) | [Instalace](sortable/installation.md), [Řazení řádků](sortable/row-sorting.md), [Řazení sloupců](sortable/column-sorting.md) |
| Core UI | [Core Akce](core/actions.md) | [Schema](core/schema/overview.md), [Modály](core/modals.md), [Notifikace](core/notifications.md), [Widgety](core/widgets.md), [Infolisty](core/infolists.md), [Pluginy](core/plugins.md), [Audit Log](core/audit.md) |
| Boost (AI) | [Přehled Boost](boost/overview.md) | [Instalace](boost/installation.md), [MCP Server a nástroje](boost/mcp-tools.md), [Guidelines a Skills](boost/guidelines-and-skills.md) |

## Rozložení zdrojů

| Cesta | Obsah |
|------|----------|
| `packages/core/src/Actions` | Action, BulkAction, HeaderAction, presety, helpery modálních akcí |
| `packages/core/src/Foundation/Schema` | Sdílený layout slovník — Grid, Section, Fieldset, Flex, Tabs/Tab, Wizard/Step, Callout, EmptyState |
| `packages/core/src/Foundation/View` | Samostatné `<x-wire::*>` Blade komponenty zrcadlící schema layouty |
| `packages/core/src/Foundation/Support` | Sdílené helpery — `ResponsiveGrid` (sloupce per breakpoint), `MobileSheet`, `EnumResolver` |
| `packages/core/src/Foundation/Concerns` | Kanonické sdílené traity — `HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasActions`, `HasSheetOnMobile`, … |
| `packages/core/src/Foundation/Registration` | `Catalog` — všechno, co aplikace zaregistrovala, ať je to cokoli — plus kontrakty `RegistrySource` / `HasRegistryKey`, kterými se k němu registr připojí |
| `packages/core/src/Foundation/Routing` | Co nese deklarace stránky (`ProvidesPages`, `RoutePage`, `ConfiguresRoutes`), `Zone` a seamy `ResolvesPageUrls` / `RegistersPageRoutes`, na které odpovídá URL konvence |
| `packages/core/src/GlobalSearch` | ⌘K paleta, její vyhledávací služba a hodnotový objekt výsledku |
| `packages/core/src/Core/Resources` | Identita resource, registr, `Workspace` a navigační slovník |
| `packages/core/src/Modals` | Modal, potvrzení, slide-over, wizard třídy |
| `packages/core/src/Notifications` | Hodnotový objekt notifikace, manager, drivery |
| `packages/core/src/Widgets` | Stats, chart, table, custom widgety |
| `packages/core/src/Infolists` | Infolist, entries, read-only zobrazení záznamu |
| `packages/core/src/Audit` | Audit záznamy, události, logger, model trait, akce audit trailu |
| `packages/core/src/Core/Plugin` | Kontrakt pluginu, manager, hooky, type registry |
| `packages/forms/src/Components` | Pole formulářů, layoutové komponenty, relační pole, repeater |
| `packages/forms/src/Forms` | Veřejné API `Form` a Livewire trait `WithForms` |
| `packages/table/src/Columns` | Třídy sloupců tabulky a sloupce inline editace |
| `packages/table/src/Filters` | Select, date, number range, ternary a vlastní filtry |
| `packages/table/src/Export` | Podpora exportů CSV, Excel, PDF |
| `packages/table/src/Concerns/WithTable.php` | Livewire integrace pro stav a akce tabulky |
| `packages/sortable/src` | Helpery sortable tabulky, Livewire trait, model pořadí sloupců |

## Testovací příkazy

```bash
composer test
composer test:core
composer test:forms
composer test:table
composer test:sortable
composer lint
composer analyse
```

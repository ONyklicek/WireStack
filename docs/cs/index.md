---
order: 10
---

# Dokumentace Wire

Uživatelská dokumentace ekosystému Wire.

## Začněte tady

| Dokument | Popis |
|----------|-------------|
| [Začínáme](getting-started.md) | Instalace Wire, nastavení Tailwindu a Livewire, první tabulka a formulář |
| [Mapa projektu](project-map.md) | Přehled balíčků, instalační cesty, rozložení zdrojů a testovací příkazy |
| [Konfigurace](configuration.md) | Publikované konfigurační soubory, proměnné prostředí a výchozí hodnoty balíčků |
| [Autorizace](authorization.md) | Gates, policies, oprávnění, pravidla tabulek a formulářů |
| [Vzhled a přizpůsobení](theming.md) | Barvy, ikony, přepis pohledů a lokalizace |
| [Testování](testing.md) | Samostatné, Livewire a unit testy pro formuláře a tabulky |
| [Kuchařka](cookbook.md) | Úlohově zaměřené recepty postavené na veřejném API |
| [Řešení potíží](troubleshooting.md) | Nápravy běžných konfiguračních problémů |
| [Návod k upgradu](upgrade.md) | Verzování, požadavky a kroky upgradu |

## wire-table

| Dokument | Popis |
|----------|-------------|
| [Přehled tabulek](table/overview.md) | První tabulka, `WithTable` a základní konfigurace |
| [Sloupce](table/columns/index.md) | Typy sloupců, formátování, hledání, řazení, responzivní viditelnost |
| [Filtry](table/filters/index.md) | Vestavěné filtry a vlastní chování dotazu |
| [Akce](table/actions.md) | Řádkové, hromadné a hlavičkové akce s modálními formuláři |
| [Exporty](table/exports.md) | Exporty CSV, Excel a PDF pro aktuální dotaz tabulky |
| [Importy](table/imports.md) | Importy CSV s mapováním hlaviček, přetypováním a validací po řádcích |
| [Souhrny](table/summaries.md) | Patičkové agregace, rozsahy, rollupy a celkové součty |
| [Seskupení řádků](table/grouping.md) | Seskupené řádky s hlavičkami a mezisoučty za skupinu |
| [Podřádky](table/sub-rows.md) | Související dětské záznamy vykreslené uvnitř řádku tabulky |
| [Správci relací](table/relation-managers.md) | Tabulky zúžené na relaci jako samostatné Livewire komponenty |
| [Notifikace](table/notifications.md) | Toasty, zpětná vazba akcí a doručovací drivery |
| [Pokročilé funkce](table/advanced.md) | Polling, souhrny, výkon a ladění |

## wire-forms

| Dokument | Popis |
|----------|-------------|
| [Přehled formulářů](forms/overview.md) | Jeden formulář, více formulářů, samostatné použití a průběh ukládání |
| [Validace](forms/validation.md) | Pravidla, zprávy a vlastní chování validace |
| [Životní cyklus ukládání](forms/save-lifecycle.md) | Validace, mutace, perzistence a notifikace |
| [Reference polí](forms/fields/index.md) | Vstupní, layoutové, zobrazovací, relační a repeater komponenty |
| [Rozšíření formulářů](forms/custom-fields.md) | Vlastní pole, zobrazovací komponenty, presety a balíčkování |

## wire-sortable

| Dokument | Popis |
|----------|-------------|
| [Přehled sortable](sortable/overview.md) | Drag and drop řazení řádků a sloupců |
| [Instalace](sortable/installation.md) | Nastavení balíčku a frontendové požadavky |
| [Reference API](sortable/api-reference.md) | API sortable tabulky a traitu |

## Core API

| Dokument | Popis |
|----------|-------------|
| [Core Foundation](core/foundation.md) | Sdílené traity, ikony, barvy a Blade helpery |
| [Core Akce](core/actions.md) | Řádkové, hromadné, hlavičkové akce, skupiny akcí |
| [Core Schema](core/schema/overview.md) | Sdílený layout slovník — Grid, Section, Flex, Tabs, Wizard, Callout, Empty State |
| [Core Notifikace](core/notifications.md) | Hodnotové objekty notifikací a drivery |
| [Core Modály](core/modals.md) | Potvrzovací, slide-over a wizard komponenty |
| [Core Widgety](core/widgets.md) | Dashboardové widgety a layout widgetů |
| [Core Infolisty](core/infolists.md) | Read-only, schématem řízené zobrazení záznamu |
| [Core Pluginy](core/plugins.md) | Rozšiřovací body aplikace a balíčků |
| [Audit Log](core/audit.md) | Záznam změn modelů a událostí spojených s tabulkami |

## wire-boost

AI nástroje pro ekosystém Wire — MCP server, guidelines a skills pro AI kódovací agenty.

| Dokument | Popis |
|----------|-------------|
| [Přehled Boost](boost/overview.md) | Co je Wire Boost a jak pomáhá AI agentům |
| [Instalace](boost/installation.md) | Instalace balíčku a konfigurace agentů |
| [MCP Server a nástroje](boost/mcp-tools.md) | MCP server a jeho dvacet introspektivních nástrojů |
| [Guidelines a Skills](boost/guidelines-and-skills.md) | Vždy načtená a on-demand vrstva AI kontextu |

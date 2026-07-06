---
title: MCP Server a nástroje
order: 30
summary: MCP server wire-boost a dvacet nástrojů, které vystavuje AI agentům.
---

# MCP Server a nástroje

Wire Boost poskytuje [MCP](https://modelcontextprotocol.io/) server postavený na
[Laravel MCP](https://github.com/laravel/mcp). Je registrován pod lokálním handle `wire-boost`
a spouští se pomocí:

```bash
php artisan wire-boost:mcp
```

Agenti ho obvykle spustí za vás z konfigurace zapsané [`wire-boost:install`](installation.md).

## WireStack nástroje

Tyto jsou důvodem, proč používat Wire Boost — nechají agenta prozkoumat vaše skutečné wire komponenty a
slovník komponent.

| Nástroj | Popis |
|------|-------------|
| `application-info` | Verze PHP / Laravel / Livewire, nainstalované verze wire balíčků a klíčový efektivní config. |
| `list-wire-components` | Objevit app Livewire komponenty, které staví wire tabulku, formulář nebo infolist. |
| `describe-table` | Vyresolvovat sloupce tabulky, filtry, header/row/bulk akce, výchozí řazení a searchability. |
| `describe-form` | Vyresolvovat zploštělé schéma polí formuláře (název, label, typ, obalující layout). |
| `describe-infolist` | Vyresolvovat schéma entries infolistu. |
| `list-component-types` | Vypsat vestavěné typy kategorie: `columns`, `filters`, `fields`, `actions`, `infolist-entries`, `widgets`, `modals`. |
| `describe-component-api` | Vypsat veřejné fluent API typu komponenty (přijímá FQCN nebo krátký název jako `badge-column`). |
| `list-icons` | Názvy ikon registrované ve wire `IconManager` (pro `->icon()`). |
| `wire-config` | Efektivní `wire-*` konfigurace nebo jeden tečkovaný klíč. |
| `search-wire-docs` | Prohledat přibalený korpus guidelines a skills pro konvence a příklady. |

### Příklad

Požádání agenta o přidání status sloupce do tabulky obvykle jde takto:

1. `list-component-types` s `category: columns` → vidí, že `badge-column` existuje.
2. `describe-component-api` s `class: badge-column` → naučí se `->color()`, `->icon()`, …
3. `describe-table` na vaší komponentě → spáruje existující konvence sloupců.
4. Napíše `BadgeColumn::make('status')->color(...)`.

## Obecné nástroje (parita s Laravel Boost)

| Nástroj | Popis |
|------|-------------|
| `database-schema` | Tabulky a sloupce, volitelně pro jednu tabulku nebo connection. |
| `database-connections` | Nakonfigurované connections a výchozí. |
| `database-query` | Vykonat read-only `SELECT` (ve výchozím stavu vypnuto). |
| `last-error` | Nejnovější chyba z log souboru. |
| `read-log-entries` | Posledních N řádků logu. |
| `get-absolute-url` | Převést relativní cestu na absolutní URL. |
| `list-artisan-commands` | Dostupné Artisan příkazy a popisy. |
| `list-routes` | Routy aplikace s metodami, URI, názvem a akcí. |
| `tinker` | Vyhodnotit PHP v kontextu aplikace (ve výchozím stavu vypnuto). |
| `browser-logs` | Nedávné záznamy browser konzole z nakonfigurovaného log souboru. |

## Bezpečnost

`database-query` a `tinker` vykonávají kód nebo čtou libovolná data, takže jsou **ve výchozím stavu vypnuté**.
Zapněte je explicitně:

```dotenv
WIRE_BOOST_DATABASE_QUERY=true
WIRE_BOOST_TINKER=true
```

Kompletní referenci `wire-boost` configu viz [Konfigurace](../configuration.md).

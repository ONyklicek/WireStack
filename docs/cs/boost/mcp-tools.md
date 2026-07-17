---
title: MCP Server a nástroje
order: 30
summary: MCP server wire-boost a nástroje, které vystavuje AI agentům.
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
| `validate-wire-component` | Postavit komponentu a nahlásit neznámé barvy, neregistrované ikony a názvy, které model neumí vyresolvovat. |
| `list-component-types` | Vypsat vestavěné typy kategorie: `columns`, `filters`, `fields`, `actions`, `infolist-entries`, `panel-entries`, `widgets`, `modals`, `layouts`. |
| `describe-component-api` | Vypsat veřejné fluent API typu komponenty — signatury, výchozí hodnoty a hodnoty, které každý parametr přijímá (FQCN nebo krátký název jako `badge-column`). |
| `list-icons` | Názvy ikon registrované ve wire `IconManager` (pro `->icon()`). |
| `wire-config` | Efektivní `wire-*` konfigurace nebo jeden tečkovaný klíč. |
| `search-wire-docs` | Prohledat kompletní wireStack dokumentaci a vrátit nejrelevantnější **sekce**. |
| `fetch-wire-doc` | Přečíst celou sekci dokumentace (nebo osnovu dokumentu) podle id. |

### Příklad

Požádání agenta o přidání status sloupce do tabulky obvykle jde takto:

1. `list-component-types` s `category: columns` → vidí, že `badge-column` existuje.
2. `describe-component-api` s `class: badge-column` → naučí se `->color()` a barvy, které přijímá.
3. `describe-table` na vaší komponentě → spáruje existující konvence sloupců.
4. Napíše `BadgeColumn::make('status')->color(...)`.
5. `validate-wire-component` na vaší komponentě → ověří, že barva, ikona i atribut existují.

## Vyhledávání v dokumentaci

V balíčku je přibalená **kompletní anglická dokumentace** — ne její shrnutí — takže nástroje fungují ve
vaší aplikaci bez přístupu k síti i bez naklonovaného wire repozitáře.

`search-wire-docs` ji indexuje po **sekcích**, ne po souborech, a řadí přes BM25 plus bonus za termy, které
sedí na nadpis sekce nebo titulek dokumentu. Výsledek nese `id`, `breadcrumb`
(`BadgeColumn > Badge Colors`), vlastnící `package` a úryvek:

```json
{
  "id": "docs/table/columns/badge.md#badge-colors",
  "breadcrumb": "BadgeColumn > Badge Colors",
  "package": "wire-table",
  "score": 25.13,
  "snippet": "Set the pill colour with the color helper."
}
```

To `id` předejte do `fetch-wire-doc` a přečtete si celou sekci. Předání id dokumentu
(`docs/table/columns/badge.md`) vrátí místo toho jeho osnovu — celé stránky mají desítky kilobajtů, takže
osnova plus cílené dotažení sekcí je levnější a přesnější. `full: true` vrátí celou stránku.

Filtrovat podle balíčku lze přes `package: wire-table` (prefix `wire-` je volitelný). Vlastní Markdown
přidáte do indexu přes `wire-boost.docs.paths`; viz [Konfigurace](../configuration.md).

## Validace

`validate-wire-component` existuje proto, že tři nejčastější chyby v generovaném wire kódu **nevyhodí
výjimku**, takže test, který ověřuje jen že se komponenta vyrenderuje, zůstane zelený:

| Chyba | Co se reálně stane |
|-------|-----------------------|
| `->color('bleu')` | `Color::resolve()` spadne zpět na šedou — badge se prostě vyrenderuje šedě. |
| `->icon('heroicon-nope')` | Na místě ikony se nevyrenderuje nic. |
| `TextColumn::make('titel')` | Buňka se vyrenderuje prázdná. |

Nástroj komponentu postaví a pak ji ověří proti kanonickým slovníkům — `Color` enum, registrované názvy
z `IconManager` a skutečné atributy modelu (sloupce, casty, `$appends`, accessory a relační cesty). Každý
nález pojmenuje cíl a navrhne, co jste nejspíš mysleli:

```json
{
  "severity": "warning",
  "rule": "unknown-attribute",
  "target": "columns.titel",
  "message": "[posts] has no attribute [titel]; it will render blank.",
  "suggestions": ["title"]
}
```

Kontrola atributů potřebuje dostupnou databázovou tabulku; bez ní se přeskočí (a nahlásí se to), místo aby
se hádalo. Sloupce, které si počítají hodnotu samy přes `->state()`, se nehlásí nikdy.

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

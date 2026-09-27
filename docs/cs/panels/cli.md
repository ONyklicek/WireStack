---
order: 80
summary: Artisan příkazy, které napíšou resource, jeho stránky, vlastní stránku a tabulku relace — a ten, který přečte, co je zaregistrované.
---

# Příkazová řádka

Všechno, z čeho se panel skládá, jde napsat ručně, a každý příkaz tady napíše
přesně to, co stránky čekají — takže první soubor je správně a zbytek je váš.
Žádný z nich nepřepíše soubor, který už máte, pokud nepředáte `--force`.

## Generování resource

```bash
php artisan make:wire-resource Order
```

napíše resource a tři stránky, které běžný resource má:

```text
app/Resources/OrderResource.php
app/Livewire/Resources/Orders/ListOrders.php
app/Livewire/Resources/Orders/CreateOrder.php
app/Livewire/Resources/Orders/EditOrder.php
```

Resource deklaruje své `pages()`, takže jakmile je zaregistrovaný a
`Route::wireResources()` běží ve skupině rout vašeho panelu, jeho URL existují.
Stránka editace se napíše s `$this->deleteHeaderAction()` v hlavičce — to je
jediný default, který stránky nechávají na vás, protože kdo smí mazat co, je
vaše pravidlo ([Stránky](pages.md#akce-v-hlavicce)).

| Volba | Co mění |
| --- | --- |
| `--model=App\Domain\Order` | Model, když to není `App\Models\{Name}` |
| `--generate` | Pole, sloupce a položky infolistu přečtené z tabulky modelu |
| `--view` | Read-only `ViewPage` a `infolist()` na resource |
| `--simple` | Jedna [`ManagePage`](pages.md#resource-na-jedne-strance) místo tří stránek |
| `--soft-deletes` | [`ManagesTrashedRecords`](pages.md#zaznamy-v-kosi) a *Obnovit* / *Trvale smazat* v hlavičkách stránek záznamu |
| `--register` | Přidá třídu do publikovaného `config/wire-core.php` |
| `--force` | Přepíše soubory, které už existují |

**`--generate` čte tabulku, ne model.** Každý sloupec se podle typu stane polem,
sloupcem a položkou — boolean `Toggle` a `BooleanColumn`, datum
`DateTimePicker::make()->asDate()` a sloupec s `->date()`, textový sloupec
`Textarea`, číslo `->numeric()`, cokoli jiného `TextInput` — a sloupec, který
není nullable ani nemá default, je `required()`. Klíč, timestampy, `deleted_at`
a přihlašovací údaje se vynechají. Je to výchozí bod napsaný jednou: tabulka,
která ještě neexistuje, napíše resource bez generovaných řádků a řekne to,
místo aby selhala.

**Na konci řekne, kde stránky jsou, nebo co chybí.** Čte aplikaci tak, jak je:
jestli je resource zaregistrovaný — vyjmenovaný v `config('wire-core.resources')`,
právě přidaný přes `--register`, nebo ve složce, kterou jmenuje
`config('wire-core.discover.resources')` — a jestli zaregistrované třídy něco
routuje — route soubor, který volá `Route::wireResources()`, nebo
panelový záznam `wire-core.routes.groups`. Když je obojí na místě, vypíše
*Ready: registered and routed at /admin/order-lines*; jinak pojmenuje jen krok,
který chybí, napsaný pro tento resource. `make:wire-page` dělá totéž pro vlastní
stránku.

**`--register` zapisuje jen tam, kde to jde bezpečně** — do publikovaného
`config/wire-core.php`, který má seznam `resources`, a jen jednou. Jinde
vypíše řádek, který je potřeba přidat.

## Generování vlastní stránky

```bash
php artisan make:wire-page TaskBoard
php artisan make:wire-page History --resource=Order
```

První napíše [`Page`](pages.md#vlastni-stranka) a pohled, který kreslí —
`app/Livewire/Pages/TaskBoard.php` a
`resources/views/livewire/pages/task-board.blade.php`. Druhý napíše stránku
o **jednom záznamu** `OrderResource`: skládá `BelongsToResource`,
`ResolvesOneRecord` a `LinksToRecordPages`, takže přijme klíč záznamu a kreslí
jeho záložky. Příkaz vypíše, co zbývá udělat: vlastní stránka se
[registruje jako resource](pages.md#registrace) — `config('wire-panels.pages')`
nebo nalezená složka — a pak se routuje a objeví v menu sama; stránka záznamu se
routuje z `pages()` svého resource:

```php
'history' => RoutePage::make(\App\Livewire\Resources\Orders\History::class)->uri('{record}/history'),
```

— a to z ní zároveň udělá jednu ze záložek záznamu. Routování je deklarace
vlastníka, takže ho příkaz vypíše, místo aby upravoval resource.

## Generování clusteru

```bash
php artisan make:wire-cluster Settings
php artisan make:wire-page Taxes --cluster=Settings
```

První zapíše `app/Clusters/Settings.php`, [cluster](clusters.md) — jednu sekci
s jednou položkou v menu a jedním prefixem URL — a řekne, jak ho zaregistrovat,
což je stejně jako stránku. Druhý zapíše stránku, která je jeho členem: stránka
jmenuje cluster vlastností `protected static ?string $cluster`. Resource se
přidá implementací `BelongsToCluster`, jedné metody, napsané ručně.

## Generování tabulky relace

```bash
php artisan make:wire-relation-manager Order items
```

napíše `app/Livewire/Resources/Orders/ItemsRelationManager.php`,
[`RelationManager`](../table/relation-managers.md) nad relací `items` s tabulkou
k doplnění, a vypíše, jak ho vložit: nechte resource implementovat
`ProvidesRelationManagers` a vracet třídu z `relationManagers()`.

## Čtení toho, co je zaregistrované

```bash
php artisan wire:resources
php artisan wire:resources orders
```

První vypíše každý zaregistrovaný resource — klíč, třídu, model, povrchy, které
deklaruje, jeho stránky a kolik rout na ně vede. Druhý ukáže jeden resource
stránku po stránce: komponentu, každou routu, která na ni vede (stránka routovaná
ve dvou zónách jsou dva řádky), její URI a oprávnění, které vyžaduje, nebo
*not routed*. Oba čtou katalog a router — tytéž odpovědi, se kterými pracuje
menu, paleta i `Route::wireResources()` — takže co vypíšou, je to, co aplikace
skutečně má.

## Změna toho, co píšou

```bash
php artisan vendor:publish --tag=wire-panels::stubs
```

zkopíruje šablony do `stubs/wire-panels/` a každý příkaz tady přečte
publikovanou šablonu dřív než svou: `resource.stub`, `resource-page.stub`,
`page.stub`, `page-view.stub`, `relation-manager.stub` a
`dashboard-page.stub`.

## Vygenerování komponenty

Části, ze kterých se skládá tabulka, formulář nebo infolist, mají vlastní
generátory v balíčku, který každou z nich vlastní — existují tedy i bez panelu:

| Příkaz | Zapíše | Balíček |
|---|---|---|
| `make:wire-column Price` | `app/Tables/Columns/PriceColumn.php` + pohled buňky | wire-table — [vlastní sloupec](../table/columns/patterns.md#vlastni-trida-sloupce) |
| `make:wire-filter Region` | `app/Tables/Filters/RegionFilter.php` + pohled ovládacího prvku | wire-table — [vlastní filtr](../table/filters/custom.md#vygenerovani) |
| `make:wire-field MoneyInput` | `app/Forms/Components/MoneyInput.php` + pohled inputu | wire-forms — [vlastní pole](../forms/custom-fields.md#stavba-vlastniho-pole) |
| `make:wire-entry Money` | `app/Infolists/Components/MoneyEntry.php` + jeho pohled | wire-core — [vlastní entry](../core/infolists/entries.md#vlastni-entry) |
| `make:wire-action Archive [--bulk]` | `app/Wire/Actions/ArchiveAction.php` | wire-core — [vlastní preset](../core/actions/index.md#vlastni-preset) |
| `make:wire-widget Revenue` | `app/Widgets/RevenueWidget.php` + jeho pohled | wire-core — [widgety](../core/widgets/index.md) |

Každý přidá svou příponu, pokud jméno už nekončí jí (pole žádnou nemá), nikdy
nepřepíše existující pohled a třídu nahradí jen s `--force`. Každý balíček
publikuje vlastní šablony — `--tag=wire-table::stubs`, `wire-forms::stubs`,
`wire-core::stubs` — do `stubs/<balíček>/`.

## Související

- [Resources](resources.md) — co vygenerovaný resource deklaruje
- [Stránky](pages.md) — co jsou vygenerované stránky
- [Routování](routing.md) — jak stránkám dát URL

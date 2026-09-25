---
order: 80
summary: Artisan příkazy, které napíšou resource, jeho stránky, vlastní stránku a relation manager — a ten, který přečte, co je zaregistrované.
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
jeho záložky. Oba vypíšou řádek, který patří do `pages()` vlastníka:

```php
'history' => RoutePage::make(\App\Livewire\Resources\Orders\History::class)->uri('{record}/history'),
```

— a u stránky záznamu je to zároveň to, co z ní udělá jednu ze záložek záznamu.
Routování je deklarace vlastníka, takže ho příkaz vypíše, místo aby upravoval
resource.

## Generování relation manageru

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

## Související

- [Resources](resources.md) — co vygenerovaný resource deklaruje
- [Stránky](pages.md) — co jsou vygenerované stránky
- [Routování](routing.md) — jak stránkám dát URL

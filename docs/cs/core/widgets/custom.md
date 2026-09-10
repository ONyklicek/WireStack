---
order: 40
summary: "Tabulka uvnitř widgetu a widget, který je jen vaše Blade view — pro panel, na který se žádný vestavěný typ nehodí."
---

# Tabulky a vlastní pohledy

Dva widgety, které drží něco, co systém widgetů nedefinuje: plnohodnotnou
[tabulku](../../table/overview.md) i s hledáním, filtry a akcemi, a obyčejné
vlastní Blade view s chromem widgetu kolem.

## TableWidget

Pár řádků tabulky uvnitř dashboardové karty.

```php
use NyonCode\WireTable\Widgets\TableWidget;
```

> **Ve 2.0 se přestěhoval mezi balíčky a předtím nekreslil nic.** Třída bývala
> `WireCore\Widgets\TableWidget`, kde si callback uložila a nikdy ho nezavolala —
> karta s nadpisem a prázdným `<div>`. Příčina byla strukturální: widgety jsou ve
> `wire-core`, tabulkový engine ve `wire-table`, a table na core závisí, takže se
> na něj z core nedalo dosáhnout. Třída teď bydlí tam, kde je engine.

### Základní použití

```php
TableWidget::make()
    ->heading('Recent orders')
    ->limit(5)
    ->table(fn (Table $table) => $table
        ->model(Order::class)
        ->columns([
            TextColumn::make('reference'),
            TextColumn::make('customer.name'),
            TextColumn::make('total')->money('CZK'),
            BadgeColumn::make('status')->colors(['paid' => 'success']),
        ]))
```

### Co kreslí a co ne

Sloupce a řádky. **Žádný toolbar, hledání, filtry, stránkování, hromadné ani
řádkové akce** — to je aparát tabulky a dashboardová karta, které narostl, je
tabulka převlečená za widget. Když má čtenář s řádky *pracovat*, sáhněte po
stránce s `WithTable`.

Co sdílí, je ta jedna věc, která se rozejít nesmí: každá buňka je
`Column::renderCell()`, takže badge, formát měny nebo cesta přes relaci se tu
vykreslí přesně jako na plné tabulce. Dotaz plánuje tentýž `TableQueryService`,
takže sloupec potřebující join nebo agregaci stojí totéž co jinde.

Protože zůstává obyčejným markupem uvnitř hostitelské komponenty, funguje
widgetu dál polling, filtr, akce v hlavičce i jeho `wire:partial` oblast — což by
vnořená Livewire komponenta nedokázala.

### Strop na řádky

```php
->limit(int $limit)   // výchozí 5, minimálně 1
```

Stránkování tu není, takže bez stropu by karta vykreslila, co vrátil dotaz.

### TableWidget API

```php
->table(Closure $callback)           // fn (Table $table): Table
->limit(int $limit)                  // počet kreslených řádků — výchozí 5
->getTableCallback(): ?Closure
->getLimit(): int
->getTable(): ?Table
->getRecords(): array
```

`emptyState()` sdílí s každým dalším widgetem — viz
[základní třída widgetu](index.md#reference-widget-api).

---

## CustomWidget

Vykreslí vlastní Blade pohled jako widget.

```php
use NyonCode\WireCore\Widgets\CustomWidget;
```

### Základní použití

```php
CustomWidget::make()
    ->heading('Quick Links')
    ->view('dashboard.quick-links')
    ->viewData(['links' => $this->getLinks()])
```

### CustomWidget API

```php
->view(string $view)                 // název Blade pohledu
->viewData(array $data)              // data předaná pohledu
->getCustomView(): ?string
```

### Kdy místo toho napsat třídu

`CustomWidget` je správná odpověď, když je panel značkování a data už jsou po
ruce. Přestává být správnou odpovědí ve chvíli, kdy má widget vlastní chování —
dotaz, který se má spustit, filtr, který se má interpretovat, hodnotu, kterou
je třeba naformátovat — protože to všechno pak žije v dashboardu, který widget
*deklaruje*, ne ve widgetu, a nejde to znovu použít, otestovat ani podědit.

Vlastní třída widgetu jsou dva soubory a generátor napíše oba:

```bash
php artisan make:wire-widget Revenue
```

Viz [Vlastní widget](index.md#vlastni-widget).

---

<a id="polling"></a>

## Související

- [Widgety](index.md) — základní třída, ze které oba dědí
- [Tabulky](../../table/overview.md) — všechno, co si tabulkový widget nechává
- [Blade komponenty](../foundation/blade-components.md) — z čeho postavit vlastní pohled
- [Dashboardy](dashboards.md) — jejich umístění vedle vestavěných typů

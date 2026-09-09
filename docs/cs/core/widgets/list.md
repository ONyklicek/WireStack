---
order: 34
summary: "Krátký feed posledních záznamů — widget, který po statistice „kolik“ odpoví na „které“, a položka, ze které je každá řádka složená."
---

# ListWidget

Panel, po kterém dashboard sáhne po svých číslech. „37 nových objednávek“ je ta
statistika; *které* objednávky je tohle. Deset řádků s názvem, detailem a časem —
posledních pár záznamů, posledních pár věcí, co se staly.

```php
use NyonCode\WireCore\Widgets\ListWidget;
```

## Jak to funguje

**Proč ne tabulka.** `TableWidget` odpovídá na větší verzi téže otázky a přináší
s sebou celý aparát tabulky: sloupce, řazení, stránkování, toolbar, vlastní
Livewire komponentu. Deset řádků typu název-a-čas nic z toho nepotřebuje a
tabulka zmenšená, dokud se nevejde do karty dashboardu, je pořád tabulka. Po
`TableWidget` sáhněte, když má čtenář s řádky *pracovat*; po tomhle, když je má
*vidět*.

**Čisté značkování.** Žádný JavaScript, žádné plátno, žádný round trip na
vykreslení. Položka s URL je opravdový `<a>`, takže feed funguje i s vypnutým
JavaScriptem a prostřední tlačítko otevře panel.

**Odkaz pokrývá celou položku.** URL udělá odkaz ze samotné řádky, ne z něčeho
uvnitř ní — odkaz obalený jen kolem názvu dá ukazovacímu zařízení cíl vysoký tři
pixely. `newTab()` s sebou nese `rel="noopener noreferrer"`, což není ozdoba: bez
`noopener` může otevřená stránka sáhnout zpět přes `window.opener`.

**Barva tónuje kolečko, ne text.** Barva položky jde na kolečko ikony. Feed se
čte jako sloupec vět a obarvit ty věty z něj udělá duhu; obarvit vedle každé z
nich kolečko o 32 pixelech nechá „selhalo“ skenovatelné a text textem. Obě třídy
pocházejí z kanonické palety, takže se název dodaný volajícím nikdy nedostane do
Tailwindu.

**Odkud se bere série.** `items()` bere pole nebo closure. Closure dostane
aktivní klíč filtru a běží při každém renderu — viz [Filtry](index.md#filtry).

**Past.** Žádné `maxItems()` neexistuje. Widget, který by sérii ořezával, by
volajícímu dovolil načíst tisíc řádků kvůli vykreslení pěti; místo, které ví,
kolik jich je potřeba, je dotaz — tam limit patří.

## Základní použití

```php
ListWidget::make()
    ->heading('Recent orders')
    ->items([
        ListItem::make('#1042')->description('Acme s.r.o.')->meta('2 minutes ago'),
        ListItem::make('#1041')->description('Bravo a.s.')->meta('18 minutes ago'),
    ])
```

## Položky, které někam vedou

```php
ListItem::make('#1042')
    ->description('Acme s.r.o.')
    ->url(route('orders.show', $order))
    ->newTab()                            // target="_blank" + rel="noopener noreferrer"
```

## Stav jedním pohledem

Ikona na tónovaném kolečku je to, co dělá feed skenovatelný bez čtení:

```php
ListItem::make('Payment failed')
    ->description('Order #1042 — card declined')
    ->icon('outline:exclamation-triangle')
    ->color('danger')
```

## Oddělovače

Vlasové linky mezi položkami jsou zapnuté. Vypněte je pro kartu, která je už tak
hustá:

```php
ListWidget::make()->items($items)->dividers(false)
```

## Prázdný stav

```php
ListWidget::make()
    ->items(fn () => $this->recentOrders())
    ->emptyState('No orders yet today.')
```

Předání `null` vrátí výchozí text (`Není co zobrazit.`, přeložený).

## Rozšířený příklad

```php
namespace App\Dashboards;

use App\Models\Order;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;

class OperationsDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            ListWidget::make()                                              // [tl! focus:start]
                ->heading('Recent orders')
                ->description('The last ten, newest first')
                ->pollingInterval('30s')
                ->items(fn () => Order::with('customer')
                    ->latest()
                    ->limit(10)                      // limit patří dotazu
                    ->get()
                    ->map(fn (Order $order) => ListItem::make($order->reference)
                        ->description($order->customer->name)
                        ->meta($order->created_at->diffForHumans())
                        ->icon($order->status->icon())
                        ->color($order->status->color())
                        ->url(route('orders.show', $order)))
                    ->all())
                ->emptyState('No orders yet today.'),                       // [tl! focus:end]
        ];
    }
}
```

## ListWidget API

```php
->dividers(bool $condition = true)   // vlasová linka mezi položkami — výchozí true
->hasDividers(): bool
```

Vše ostatní, co seznamový widget bere, je sdílené a zdokumentované centrálně:
`items()`, `emptyState()`, `filter()`, `lazy()`, `heading()`, `columnSpan()`,
`pollingInterval()` a settery autorizace jsou na
[základní třídě widgetu](index.md#reference-widget-api).

---

## ListItem

Jedna položka: co se stalo, volitelně kdy a volitelně kam jít se na to podívat.

```php
use NyonCode\WireCore\Widgets\ListItem;
```

### Úplný příklad

```php
ListItem::make('Order #1042')
    ->description('Acme s.r.o. — 12 400 Kč')
    ->meta('2 minutes ago')
    ->icon('outline:shopping-cart')
    ->color('success')
    ->url(route('orders.show', $order))
    ->newTab()
    ->extraAttributes(['data-testid' => 'order-1042'])
```

### ListItem API

Vytváří se přes `ListItem::make(string $title)`.

```php
->description(?string $description)   // druhý řádek pod názvem
->meta(?string $meta)                 // doprava zarovnaná poznámka — čas, počet, stavové slovo
->icon(string|Icon|null $icon)        // ikona na tónovaném kolečku
->color(?string $color)               // libovolný klíč palety — tónuje kolečko
->url(?string $url)                   // udělá z celé položky odkaz
->newTab(bool $condition = true)      // otevřít v novém panelu, s rel="noopener noreferrer"
->extraAttributes(array $attrs)       // vlastní HTML atributy na položce
->getTitle(): string
->getDescription(): ?string
->getMeta(): ?string
->getIcon(): ?string
->getColor(): ?string
->getUrl(): ?string
->opensInNewTab(): bool
```

## Související

- [Tabulky a vlastní pohledy](custom.md) — `TableWidget`, když se s řádky má pracovat, ne je jen číst
- [Progress](progress.md) — druhý čistě CSS widget, pro čísla mířící k cíli
- [Notifikace](../notifications/index.md) — druhý feed v tomhle frameworku, a jiný
- [Widgety](index.md) — sdílený základ: polling, filtry, odklad, autorizace

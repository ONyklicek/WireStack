---
order: 34
summary: "A short feed of recent records — the widget that answers \"which ones\" after a stat has said how many, and the item each entry is built from."
---

# ListWidget

The panel a dashboard reaches for after its figures. "37 new orders" is the stat;
*which* orders is this. Ten rows of title, detail and timestamp — the last few
records, the last few things that happened.

```php
use NyonCode\WireCore\Widgets\ListWidget;
```

## How It Works

**Why not a table.** `TableWidget` answers a bigger version of the same question
and brings a table's whole apparatus with it: columns, sorting, pagination, a
toolbar, a Livewire component of its own. Ten rows of title-and-timestamp need
none of that, and a table shrunk until it fits a dashboard card is still a table.
Reach for `TableWidget` when the reader needs to *work* with the rows; reach for
this when they need to *see* them.

**Pure markup.** No JavaScript, no canvas, no round trip to draw. An entry with a
url is a real `<a>`, so the feed works with JavaScript off and a middle-click
opens a tab.

**The link covers the whole entry.** A url turns the row itself into the anchor
rather than adding one inside it — a link wrapped around only the title gives a
pointing device a target three pixels tall. `newTab()` carries
`rel="noopener noreferrer"` with it, which is not decoration: without `noopener`
the opened page can reach back through `window.opener`.

**Colour tints the disc, not the text.** An entry's colour goes on the icon's
disc. A feed is read as a column of sentences, and colouring the sentences turns
it into a rainbow; colouring a 32-pixel disc beside each one keeps "failed"
scannable while the text stays text. Both classes come from the canonical
palette, so a caller-supplied name never reaches Tailwind.

**Where the series comes from.** `items()` takes an array or a closure. The
closure receives the active filter key and runs on every render — see
[Filters](index.md#filters).

**The trap.** There is no `maxItems()`. A widget that trimmed the series would
let a caller load a thousand rows to draw five; the place that knows how many are
wanted is the query, so the limit goes there.

## Basic Usage

```php
ListWidget::make()
    ->heading('Recent orders')
    ->items([
        ListItem::make('#1042')->description('Acme s.r.o.')->meta('2 minutes ago'),
        ListItem::make('#1041')->description('Bravo a.s.')->meta('18 minutes ago'),
    ])
```

## Entries That Link Somewhere

```php
ListItem::make('#1042')
    ->description('Acme s.r.o.')
    ->url(route('orders.show', $order))
    ->newTab()                            // target="_blank" + rel="noopener noreferrer"
```

## Status At A Glance

An icon on a tinted disc is what makes a feed scannable without reading it:

```php
ListItem::make('Payment failed')
    ->description('Order #1042 — card declined')
    ->icon('outline:exclamation-triangle')
    ->color('danger')
```

## Dividers

Hairlines between entries are on by default. Turn them off for a card that is
already dense:

```php
ListWidget::make()->items($items)->dividers(false)
```

## Empty State

```php
ListWidget::make()
    ->items(fn () => $this->recentOrders())
    ->emptyState('No orders yet today.')
```

Passing `null` restores the default (`Nothing to show.`, translated).

## Extended Example

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
                    ->limit(10)                      // the limit belongs to the query
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
->dividers(bool $condition = true)   // hairline between entries — default true
->hasDividers(): bool
```

Everything else a list widget takes is shared and documented centrally:
`items()`, `emptyState()`, `filter()`, `lazy()`, `heading()`, `columnSpan()`,
`pollingInterval()` and the authorization setters are on the
[widget base](index.md#widget-api-reference).

---

## ListItem

One entry: what happened, optionally when, optionally where to go to see it.

```php
use NyonCode\WireCore\Widgets\ListItem;
```

### Full Example

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

Built with `ListItem::make(string $title)`.

```php
->description(?string $description)   // secondary line under the title
->meta(?string $meta)                 // right-aligned aside — a timestamp, a count, a status word
->icon(string|Icon|null $icon)        // icon on the tinted disc
->color(?string $color)               // any palette color key — tints the disc
->url(?string $url)                   // makes the whole entry a link
->newTab(bool $condition = true)      // open it in a new tab, with rel="noopener noreferrer"
->extraAttributes(array $attrs)       // custom HTML attributes on the entry
->getTitle(): string
->getDescription(): ?string
->getMeta(): ?string
->getIcon(): ?string
->getColor(): ?string
->getUrl(): ?string
->opensInNewTab(): bool
```

## Related

- [Tables And Custom Views](custom.md) — `TableWidget`, when the rows have to be worked with rather than read
- [Progress](progress.md) — the other pure-CSS widget, for figures heading toward a target
- [Notifications](../notifications/index.md) — the other feed in this framework, and a different one
- [Widgets](index.md) — the shared base: polling, filters, deferral, authorization

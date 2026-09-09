---
order: 32
summary: "Rows of a fill against a track — how far a figure is toward the figure it is supposed to reach, and the item each row is built from."
---

# ProgressWidget

The panel for a figure that is *heading somewhere*: a sales quota, a budget
spent, disk filling up, a sprint burning down. A stat card says a number and a
bar chart compares numbers against each other; neither says how far along the way
a number is, which is the one question a fill against a track answers at a glance.

```php
use NyonCode\WireCore\Widgets\ProgressWidget;
```

## How It Works

**Pure CSS, resolved on the server.** Like `BarChartWidget` and unlike
`ChartWidget`, there is no Chart.js, no canvas and nothing to wait for. Each
row's geometry is `ProgressItem::getPercentage()`, computed in PHP — arithmetic
in a template is arithmetic nothing can test.

**The fraction, and its three edges.** `value / target × 100`, clamped to 0–100
on both ends:

| Reading | Drawn as | Why |
| --- | --- | --- |
| `value(300)->target(120)` | a full bar | The fill is a width inside a fixed box; an over-delivered target must not draw a bar wider than its track |
| `value(-40)->target(120)` | an empty bar | Not a bar growing to the left |
| `target(0)` | an empty bar | "0 of 0" is an unconfigured row far more often than a finished one, and a full bar would announce a success nobody had |

**What each row prints.** The rounded percentage, unless `formattedValue()` says
otherwise. Deliberately *not* `number_format($value)`: a thousands separator and
a decimal mark are a locale decision, and a widget is the wrong place to make one
on the caller's behalf. A caller who wants `1.2M / 2M` writes exactly that.

**Where the series comes from.** `items()` takes an array or a closure. The
closure receives the widget's active filter key, runs on every render, and is
never memoised — so a filter on this widget actually re-resolves the data. See
[Filters](index.md#filters).

**What it costs.** One view render for the widget, whatever the row count. There
is no per-row view: the rows are a `@foreach` inside the widget's own template.

**The trap.** The fill's width is an inline `style`, not a Tailwind class. A
percentage is a continuous value and a utility class is a fixed set, so
`w-[73.4%]` would need the JIT to have seen that exact number at build time. The
number is resolved and clamped in PHP and never reaches a class name, so nothing
a caller supplies can inject one.

## Basic Usage

```php
ProgressWidget::make()
    ->heading('Quarterly targets')
    ->items([
        ProgressItem::make('New MRR')->value(84_000)->target(120_000)->color('success'),
        ProgressItem::make('Churn budget')->value(31)->target(40)->color('warning'),
    ])
```

## Values On The Rows

Each row prints its reading beside the label. On a dense board where the fills
are the comparison, turn it off and let the bars speak:

```php
ProgressWidget::make()
    ->items($items)
    ->showValues(false)
```

## Formatting A Reading

```php
ProgressItem::make('Storage')
    ->value(412)
    ->target(1_000)
    ->formattedValue('412 GB / 1 TB')   // printed instead of "41%"
```

## Empty State

A widget whose query came back with nothing shows a sentence rather than an empty
card:

```php
ProgressWidget::make()
    ->items(fn () => $this->quotas())
    ->emptyState('No targets set for this quarter.')
```

Passing `null` restores the default (`Nothing to show.`, translated).

## Accessibility

Each track is a `role="progressbar"` carrying `aria-valuenow`, `aria-valuemin`
and `aria-valuemax`, labelled by the row's label. The printed value is marked
`aria-hidden` because the same reading is already on the bar — without that, a
screen reader announces every row twice.

## Extended Example

```php
namespace App\Dashboards;

use App\Models\Deal;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;

class SalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            ProgressWidget::make()                                        // [tl! focus:start]
                ->heading('Quota attainment')
                ->description('Closed-won against target, per rep')
                ->columnSpan('full')
                ->filter(['quarter' => 'This quarter', 'year' => 'This year'])
                ->items(fn (?string $range) => Deal::quotaProgress($range)
                    ->map(fn (array $row) => ProgressItem::make($row['rep'])
                        ->value($row['closed'])
                        ->target($row['quota'])
                        ->formattedValue($row['closed_formatted'].' / '.$row['quota_formatted'])
                        ->description($row['deals'].' deals')
                        ->color($row['closed'] >= $row['quota'] ? 'success' : 'primary'))
                    ->all())
                ->emptyState('No quotas configured.'),                    // [tl! focus:end]
        ];
    }
}
```

## ProgressWidget API

```php
->showValues(bool $condition = true)   // print each row's reading beside its label — default true
->showsValues(): bool
```

Everything else a progress widget takes is shared and documented centrally:
`items()`, `emptyState()`, `filter()`, `lazy()`, `heading()`, `columnSpan()`,
`pollingInterval()` and the authorization setters are on the
[widget base](index.md#widget-api-reference).

---

## ProgressItem

One row: a label, a reading, the reading it is heading for.

```php
use NyonCode\WireCore\Widgets\ProgressItem;
```

### Full Example

```php
ProgressItem::make('New MRR')
    ->value(84_000)
    ->target(120_000)
    ->formattedValue('84K / 120K')
    ->description('vs. 71K last quarter')
    ->icon('outline:arrow-trending-up')
    ->color('success')
    ->extraAttributes(['data-testid' => 'mrr'])
```

### ProgressItem API

Built with `ProgressItem::make(string $label)`.

```php
->value(int|float $value)                 // the current reading — default 0
->target(int|float $target)               // what it is heading for — default 100
->description(?string $description)       // secondary line under the label
->formattedValue(?string $value)          // printed instead of the percentage
->icon(string|Icon|null $icon)            // icon beside the label
->color(?string $color)                   // any palette color key ('success', 'danger', …)
->extraAttributes(array $attrs)           // custom HTML attributes on the row
->getLabel(): string
->getValue(): float
->getTarget(): float
->getPercentage(): float                  // 0–100, clamped
->isComplete(): bool                      // the reading has reached its target
->getFormattedValue(): string
->getDescription(): ?string
->getIcon(): ?string
->getColor(): ?string
```

## Related

- [Stats](stats.md) — a figure with its trend, when there is no target to reach
- [Charts](charts.md) — `BarChartWidget`, when the comparison is between the figures rather than against a target
- [Lists](list.md) — the other pure-CSS widget, for entries rather than figures
- [Widgets](index.md) — the shared base: polling, filters, deferral, authorization

---
order: 83
summary: One stop on a tour — the element hook it points at, how it is narrowed to one element among many, and what the panel beside it says.
---

# TourStep

One stop on a [Tour](tours.md): the element it points at, and what the panel
beside that element says. A step names an **element hook**, the `data-wire`
name the framework writes on its markup, and never a CSS selector.

```php
use NyonCode\WireCore\Tours\TourStep;
```

## How It Works

**What a step points at.** `TourStep::make('table-search')` becomes the selector
`[data-wire="table-search"]`. The browser looks that selector up when the tour
starts, and again before each step is shown. The first element that matches is
the one the panel points at, **as long as it is showing**. Much of the
framework's markup is rendered and then hidden until it is needed: the
bulk-action bar until rows are selected, a dropdown until it opens. An element
like that is in the page, but it has no size, so the step is treated as if the
element were missing.

**Why a hook and not a selector.** A hook name is public API. The theming guide
promises that a name may be added and is not renamed or removed in a minor
release, and `npm run hooks:verify` holds the framework to it. A class, an id or
a structural selector like `.mt-4 > div` has no such promise, so a tour written
against one could break in a release nobody thought was breaking. It is not
`data-testid` either. The two carry the same name where both are present, but a
test id is free to change.

**Checked when it is made.** `make()` throws a `TourDefinitionException` for a
name that is not a well-formed hook name (kebab-case: lowercase letters and
digits, single hyphens, starting with a letter). The check is there because the
browser **skips a step whose element is missing or hidden**. That is on purpose,
so a tour keeps working when an application hides a control. Without the check,
a typo would be skipped the same way and the tour would get shorter without
anyone noticing. The check can only catch a malformed name. A well-formed name
that nothing on the page renders is skipped, like any missing element.

**Where it runs.** Nothing about a step reaches the server after the page has
rendered. Its selector, heading, text and placement are sent with the page, and
the browser does the rest.

**Defaults.** No heading, no text, placement `bottom`, no narrowing.

## Basic Usage

```php
TourStep::make('table-search')
    ->heading('Find a row')
    ->text('Type here to narrow the table down.');
```

## Content

`heading()` is the bold line at the top of the panel, and `text()` is the one or
two sentences under it. Either can be left out, or cleared with `null`. They are
plain text, not HTML:

```php
TourStep::make('table-column-toggle')
    ->heading('Your columns')
    ->text('Hide the ones you never read. The table remembers your choice.');
```

Translate them as you would any other string:

```php
TourStep::make('admin-sidebar')->text(__('tours.sidebar'));
```

## Placement

Where the panel sits relative to its element. The value is passed to Floating UI
unchanged: `top`, `right`, `bottom` or `left`, optionally followed by `-start` or
`-end`. If there is no room on the side you asked for, Floating UI moves the
panel to the opposite side.

```php
TourStep::make('admin-sidebar')->placement('right-start');   // beside a tall element, top-aligned
TourStep::make('admin-user')->placement('bottom-end');       // under something in the top-right corner
```

The value is not validated. Only an empty value is replaced, with `bottom`.
Anything else is passed through as written.

## Narrowing to One Element

One hook name can be on many elements at once. Every entry in the sidebar is an
`admin-nav-item`. `where()` adds a second attribute that picks one of them out:

```php
TourStep::make('admin-nav-item')->where('resource', 'orders');
// [data-wire="admin-nav-item"][data-resource="orders"]
```

The attribute name is written without its `data-` prefix and must be a
well-formed hook-style name, or `where()` throws. Its value is escaped, so a key
containing a quote cannot break the selector. Several `where()` calls combine
with AND, in the order you wrote them.

This works for `admin-nav-item` because its view writes `data-resource` next to
the hook. Narrow only by attributes that sit on the same element as the hook.

## Extended Example

A first-run tour whose steps follow the page from left to right, including one
that points at a single entry among many in the sidebar:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use NyonCode\WireCore\Tours\Tour;
use NyonCode\WireCore\Tours\Tours;
use NyonCode\WireCore\Tours\TourStep;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(Tours::class)->register(
            Tour::make('orders-first-run')
                ->resource('orders')
                ->page('index')
                ->steps([
                    TourStep::make('admin-nav-item')             // [tl! focus:start]
                        ->where('resource', 'orders')
                        ->heading('Orders')
                        ->text('You are here. Every order the shop has taken is in this list.')
                        ->placement('right'),

                    TourStep::make('table-search')
                        ->heading('Find one')
                        ->text('Search by customer, number or e-mail.')
                        ->placement('bottom-start'),

                    TourStep::make('table-bulk-bar')             // hidden until rows are selected — skipped
                        ->text('Select several rows to act on them at once.'),

                    TourStep::make('table-column-toggle')
                        ->heading('Your columns')
                        ->text('Hide the ones you never read.')
                        ->placement('bottom-end'),               // [tl! focus:end]
                ]),
        );
    }
}
```

`table-bulk-bar` is on the page but hidden until rows are selected, so on a
first visit the step is skipped and the counter shows three steps, not four.

## TourStep API

```php
TourStep::make(string $anchor)             // an element-hook name; throws if it is not a well-formed one
->heading(?string $heading)                // bold line at the top of the panel — default none
->text(?string $text)                      // the body, plain text — default none
->placement(string $placement)             // 'top'|'right'|'bottom'|'left', optionally '-start'|'-end' — default 'bottom'
->where(string $attribute, string $value)  // narrow by data-<attribute>="<value>"; the name must be hook-shaped
->getAnchor(): string
->getHeading(): ?string
->getText(): ?string
->getPlacement(): string
->getSelector(): string                    // the CSS selector the browser resolves, narrowing included
```

## Related

- [Tour](tours.md) — who sees a tour, where it runs, and how it is remembered
- [Theming](../start/theming.md#styling-hooks) — where hook names come from, and what they promise

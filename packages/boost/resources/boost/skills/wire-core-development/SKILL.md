---
name: wire-core-development
description: Build wire-core features — actions, modals, notifications, infolists, widgets, guided tours, icons and colors — using the shared Foundation concerns.
---

# wire-core Development

## When to use this skill

Use when adding actions, modals, notifications, infolists, widgets or guided tours, or when working with
the shared color/icon/size/visibility vocabulary.

## Workflow

1. Identify the canonical owner of the behaviour. Shared semantics live once in `wire-core` Foundation
   concerns (`HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, `HasName`, `HasLabel`).
2. Use `describe-component-api` to see an action/entry/widget fluent surface and `list-icons` for names.
3. Compose objects fluently; render reusable markup from PHP via `Htmlable` helpers.

## Patterns

```php
// Action with confirmation + modal
EditAction::make()
    ->icon('pencil')
    ->color('primary')
    ->modal(Modal::make()->heading('Edit'));

// Infolist
Infolist::make()->schema([
    TextEntry::make('name'),
    ColorEntry::make('brand_color'),
    RepeatableEntry::make('items')->schema([TextEntry::make('label')]),
]);

// Stats widget
StatsOverviewWidget::make()->stats([
    Stat::make('Users', (string) User::count())->icon('users')->color('success'),
]);

// Guided tour, registered in a provider's boot(); runs once per person on the sales dashboard
app(Tours::class)->register(
    Tour::make('sales-intro')->zones('sales')->resource('overview')->steps([
        TourStep::make('admin-nav-item')->where('resource', 'overview')->heading('Your overview'),
        TourStep::make('widget-layout-edit')->text('Rearrange the cards.'),
        TourStep::make('table-search')->on('orders')->heading('Every list works like this'),
    ]),
);
```

## Rules

- Extend existing Foundation concerns instead of duplicating `match` maps or resolvers.
- Attach modals/confirmations to actions rather than hand-rolling modal state.
- Keep distinct UI surfaces (button, badge, banner) as separate reusable resolvers that share semantics.
- A tour step names an element hook (`data-wire`), never a CSS selector; a step whose element is hidden,
  or whose `on()` page this person may not open, is skipped. Change `->since()` to show a tour again,
  never the id. Phones get a docked panel; a tour left halfway reopens at the step reached.

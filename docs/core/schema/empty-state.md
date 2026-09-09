---
order: 21
api_class: NyonCode\WireCore\Foundation\Schema\EmptyState
summary: "What a surface shows when there is nothing to show: an icon, a heading, a sentence, and the actions that fix it."
---

# Empty State

The screen a user meets before they have created anything. `EmptyState` is a
centred icon, a heading, a sentence and — the part that matters — the buttons
that get them out of it. Reach for it wherever a list can legitimately be empty,
and say what to do next rather than only that there is nothing here.

```php
use NyonCode\WireCore\Foundation\Schema\EmptyState;
```

## How It Works

An empty state is a **layout component**: no value, no state path. It renders one
centred column — a soft grey circle holding the icon, then a heading, then a
description, then a row of actions.

**Every part is optional and independently omitted.** No icon, no circle. Neither
a heading nor a description, and the whole text block disappears. No actions, no
button row. An `EmptyState::make()` with nothing set renders an empty centred
`div`, which is why the useful minimum is a heading plus one action.

**The description only gets its top margin when a heading is above it**, so a
description on its own sits where the heading would have been rather than
floating below a gap.

**Actions are pre-rendered HTML, not action objects.** This is the part that
surprises people, and it is worth being precise about: the setter is typed
`Htmlable|string`, but the render path casts each entry with `(string) $action`.
An object is therefore only accepted if it has `__toString()` — and the
framework's `Action` implements `Htmlable` **without** being `Stringable`, so
passing one directly throws:

```text
Error: Object of class NyonCode\WireCore\Actions\Action could not be converted to string
```

Hand it over rendered instead — `->toHtml()`, or a Blade string of your own:

```php
->actions([$action->toHtml()])
```

The table's "no records" state and the standalone `<x-wire::empty-state>` tag
render through the same partial, so whatever you learn here holds in all three.

## Basic Usage

```php
EmptyState::make()
    ->icon('outline:inbox')
    ->heading('No orders yet')
    ->description('Orders will appear here as soon as your first customer checks out.')
```

## With Something To Do About It

```php
use NyonCode\WireCore\Actions\Action;

EmptyState::make()
    ->icon('outline:users')
    ->heading('No team members')
    ->description('Invite someone to collaborate on this project.')
    ->actions([
        Action::make('invite')->label('Invite a teammate')->toHtml(),   // [tl! focus]
    ])
```

Note the `->toHtml()`. Buttons wrap onto more rows if you pass several, and the
row is centred under the description.

## A Closure Instead Of A String

`heading()` and `description()` both accept a closure, resolved on read, so the
message can depend on what the user is looking at:

```php
EmptyState::make()
    ->icon('outline:magnifying-glass')
    ->heading(fn (): string => "Nothing matches \"{$this->search}\"")   // [tl! focus]
    ->description('Try a shorter search, or clear the filters.')
```

## Extended Example

A dashboard panel that shows either its content or an empty state, inside a real
Livewire host:

```php
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Schema\EmptyState;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class ProjectOverview extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Section::make('members')
                    ->label('Team')
                    ->schema([
                        EmptyState::make()                                  // [tl! focus:start]
                            ->icon('outline:users')
                            ->heading('No team members yet')
                            ->description('Invite someone to collaborate on this project.')
                            ->actions([
                                Action::make('invite')
                                    ->label('Invite a teammate')
                                    ->action(fn () => $this->invite())
                                    ->toHtml(),
                            ])
                            ->visible(fn (): bool => $this->members->isEmpty()),
                    ]),                                                      // [tl! focus:end]
            ]);
    }

    public function invite(): void
    {
        // ...
    }
}
```

`visible()` comes from the shared layout surface, so the empty state is absent
from the markup entirely once there is something to show.

## Standalone Tag

The same partial as a Blade tag, for a surface that has no schema:

```blade
<x-wire::empty-state icon="outline:inbox" heading="No records yet" />
```

A table's own empty state is configured on the table rather than built here —
see [Tables](../../table/overview.md).

## EmptyState API

```php
->icon(string|Icon|null $icon)                     // shown in a soft circle above the heading
->heading(string|Closure|null $heading)            // the primary line
->description(string|Closure|null $description)    // the secondary line under it
->actions(array $actions)                          // pre-rendered HTML strings — see How It Works
->getIcon(): ?string
->getHeading(): ?string
->getDescription(): ?string
->getActionsHtml(): array
```

Everything else — `visible()`, `hidden()`, `columnSpan()`, `schema()` — is the
shared layout surface. See [Common Layout API](overview.md#common-layout-api).

## Related

- [Schema](overview.md) — the vocabulary this belongs to, and the shared surface
- [Callout](callout.md) — the other prime component
- [Actions](../actions/index.md) — what goes in the button row
- [Tables](../../table/overview.md) — the table's own "no records" state

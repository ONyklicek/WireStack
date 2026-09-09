---
order: 10
summary: A collapsible section with a heading, a description and an icon, grouping the components under it.
---

# Section

A card with a heading. `Section` is the workhorse of any form long enough to
scroll: it gives a group of fields a title, an optional sentence explaining it,
its own column grid, buttons in its header, and — the part people actually reach
for — a fold, so the parts of a form a user rarely touches can start out of the
way.

```php
use NyonCode\WireCore\Foundation\Schema\Section;
```

## How It Works

A section is a **layout component**: no value, no state path, safe to add or
remove without touching data. It renders a bordered, padded card, an optional
header block, and its children in a grid below.

**The header only exists if something goes in it.** No label, no description and
no header actions means no header block at all — a bare card. This is worth
knowing because `Section::make('billing')` alone does *not* headline its name
into a heading the way a field does. Write `->label('Billing')`.

**Folding is Alpine, not Livewire.** `collapsible()` puts `x-data="{ open }"` on
the card and `x-show` + `x-collapse` on the body, so opening and closing is
instant and costs no round trip. Two things follow from that:

- **The fold is not persisted.** A section reopens in its declared state after a
  `wire:navigate` or a full page load. If a user's choice must survive, that is
  application state, not a section setting.
- **The whole header is the toggle** — it carries `@click`, `role="button"` and
  `:aria-expanded`. Header actions are wrapped in `@click.stop` so pressing one
  runs the action instead of folding the card, which is the bug this prevents.

**`collapsed()` implies `collapsible()`.** Setting only "starts folded" would
give the user a section they cannot open, so the concern turns the other flag on
for you. Both accept a closure, evaluated on read — a section that folds for one
role and not another is a condition, not a constant.

**Columns resolve through the canonical `ResponsiveGrid`**, exactly as
[`Grid`](grid.md) does: an int is a mobile-first reflow at `md`, a map
(`['default' => 1, 'lg' => 3]`) says every breakpoint itself, counts clamped to
1–12. The default is **1 column**. (This is where `Section` and
[`Fieldset`](fieldset.md) differ — the fieldset still resolves its int case
locally and stops at four.)

`aside()` turns the card into a three-column grid from `md` up: the header takes
one column, the body spans two. Below `md` it stacks, so it is a desktop shape
only. Folding, the column grid and header actions all keep working inside it,
because it wraps the two blocks that already exist rather than replacing them.

`compact()` is only padding — `p-3` instead of `p-4 sm:p-6`.

## Basic Usage

```php
Section::make('personal')
    ->label('Personal information')
    ->description('Basic details about the user.')
    ->icon('outline:user')
    ->columns(2)
    ->schema([
        TextInput::make('name')->required(),
        TextInput::make('email')->email()->required(),
    ])
```

## Folding

```php
Section::make('advanced')
    ->label('Advanced settings')
    ->collapsed()          // [tl! focus]
    ->schema([
        Toggle::make('debug_mode'),
        TextInput::make('webhook_url')->url(),
    ])
```

`collapsed()` is the one to write when you want a section out of the way at
first. Use `collapsible()` alone when it should start open but still be foldable.
Both take a closure:

```php
Section::make('internal')
    ->label('Internal notes')
    ->collapsed(fn (): bool => ! auth()->user()->isAdmin())   // [tl! focus]
    ->schema([...])
```

## Buttons In The Header

`headerActions()` puts [actions](../../actions/index.md) beside the heading —
"Regenerate key", "Send test e-mail", the things that belong to this group rather
than to the form as a whole:

```php
Section::make('api')
    ->label('API access')
    ->headerActions([                                     // [tl! focus:start]
        Action::make('regenerate')
            ->label('Regenerate token')
            ->requiresConfirmation()
            ->action(fn () => $this->regenerateToken()),
    ])                                                     // [tl! focus:end]
    ->schema([
        TextInput::make('api_token')->disabled(),
    ])
```

It is an alias for the shared `actions()`, with header-slot semantics.

## Heading Beside The Fields

`aside()` moves the heading and description into a left column, with the fields
on the right — the shape settings screens use when each group needs a sentence of
explanation:

```php
Section::make('notifications')
    ->label('Notifications')
    ->description('Choose how and when we contact you.')
    ->aside()                                              // [tl! focus]
    ->schema([
        Toggle::make('email_notifications'),
        Toggle::make('sms_notifications'),
    ])
```

One third heading, two thirds fields, from `md` up; stacked below it.

## Extended Example

A settings page in a real Livewire host: one plain section, one aside section,
and one that starts folded.

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class AccountSettings extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Section::make('profile')                         // [tl! focus:start]
                    ->label('Profile')
                    ->icon('outline:user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                    ]),

                Section::make('notifications')
                    ->label('Notifications')
                    ->description('Choose how and when we contact you.')
                    ->aside()
                    ->schema([
                        Toggle::make('email_notifications'),
                        Toggle::make('sms_notifications'),
                    ]),

                Section::make('danger')
                    ->label('Advanced')
                    ->collapsed()
                    ->schema([
                        TextInput::make('webhook_url')->url(),
                    ]),                                           // [tl! focus:end]
            ])
            ->successMessage('Settings saved');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

## Section API

```php
->description(string|Closure|null $description)  // the sentence under the heading
->icon(string|Icon|null $icon)                   // icon before the heading text
->columns(int|array $columns)                    // 1 (default), or ['default' => 1, 'lg' => 3]
->collapsible(bool|Closure $condition = true)    // the header folds the card
->collapsed(bool|Closure $condition = true)      // start folded — implies collapsible()
->compact(bool $condition = true)                // tighter padding
->aside(bool $condition = true)                  // heading beside the fields from md up
->headerActions(array $actions)                  // ActionContract[] in the header; alias of actions()
->getDescription(): ?string
->getIcon(): ?string
->getColumns(): int|array
->getHeaderActions(): array
->isCompact(): bool
->isAside(): bool
->isCollapsible(): bool
->isCollapsed(): bool
```

Everything else — `label()`, `schema()`, `visible()`, `disabled()`,
`columnSpan()` — is the shared layout surface. See
[Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Grid](grid.md) — the same column grid, without the card
- [Fieldset](fieldset.md) — a legend instead of a heading, when the grouping is semantic
- [Tabs](tabs.md) — when the groups are alternatives rather than a sequence
- [Actions](../../actions/index.md) — what goes in `headerActions()`

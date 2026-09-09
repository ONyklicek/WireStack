---
summary: Raw markup inside a schema, and the static helpers that build the common pieces of it.
---

# Html

Markup dropped straight into the schema. Reach for `Html` when a form needs
something that is not a field and not a sentence — a rule between two groups, a
heading the layout components do not give you, a block of your own markup. For a
value with a label above it use [`Placeholder`](placeholder.md); for a whole
partial use [`ViewField`](view-field.md).

```php
use NyonCode\WireForms\Components\Display\Html;
```

## How It Works

`Html` is a **display component**: it renders output and never binds to form
state. No state path, no validation, nothing submitted.

**Its content is rendered unescaped, always.** There is no `escape()` switch here
— unlike [`Placeholder`](placeholder.md), which escapes by default, and
[`ViewField`](view-field.md), which lets you choose. What you set is what reaches
the page:

```php
Html::make()->content($userSuppliedString)   // never do this
```

Anything with user input in it must be sanitised before it gets here, or belong
in a component that escapes.

**The name is optional**, which is unusual — `Html::make()` with no argument
generates `html_<uniqid>` for itself, because a component that holds no state has
nothing to be addressed by. Pass a name only if you want one for your own
reference.

**The static factories are the safe path.** `divider()`, `spacer()`, `heading()`
and `paragraph()` do not concatenate strings: each sets `content()` to a closure
that renders a Blade partial, and the partial escapes the text with `{{ }}`. So
`Html::heading($fromTheDatabase)` is safe in a way `Html::make()->content(...)`
is not.

Two consequences of it being a closure rather than a rendered string: the markup
is only built for a component that is actually shown, and the partials —
`wire-forms::components.html.*` — are the publish-and-override point, like every
other view this package ships.

**`heading()` picks its own type scale** from the level: `1` is `text-2xl
font-bold`, `2` (the default) `text-xl font-semibold`, `3` `text-lg font-medium`,
and anything else `text-base font-medium`. The level decides the tag as well, so
`heading('Billing', 3)` is an `<h3>`.

## Basic Usage

```php
Html::make()
    ->content('<div class="rounded bg-gray-50 p-3 text-sm">Anything you like.</div>')
```

## The Static Helpers

```php
Html::divider();                       // <hr> with vertical margin
Html::spacer('8');                     // an empty div, Tailwind height step
Html::heading('Billing details');      // <h2>, the default level
Html::heading('Card', 3);              // <h3>
Html::paragraph('We never store card numbers.');
```

These are the ones to reach for by default: they escape their text, and their
markup is one published partial rather than a string in your schema.

## Breaking A Long Form Up

```php
Html::heading('Contact'),              // [tl! focus]
TextInput::make('email')->email(),
TextInput::make('phone'),

Html::divider(),                       // [tl! focus]

Html::heading('Billing'),              // [tl! focus]
TextInput::make('vat_number'),
```

A [`Section`](../../core/schema/layout/section.md) does this with a card, a fold
and header actions; `Html::heading()` plus `Html::divider()` is the flat version,
for a form that wants the rhythm without the boxes.

## Extended Example

A settings form in a real Livewire host, using the factories for structure and
one raw block for something the framework does not ship:

```php
use Livewire\Component;
use NyonCode\WireForms\Components\Display\Html;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class WorkspaceSettings extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(Workspace::class)
            ->schema([
                Html::heading('General'),                            // [tl! focus:start]
                Html::paragraph('Everyone in the workspace sees these.'),

                TextInput::make('name')->required(),
                TextInput::make('slug')->required(),

                Html::divider(),

                Html::heading('Danger zone', 3),
                Html::make()->content(
                    '<p class="text-sm text-red-600">Deleting a workspace cannot be undone.</p>'
                ),                                                    // [tl! focus:end]

                Toggle::make('scheduled_for_deletion')
                    ->label('Schedule this workspace for deletion'),
            ])
            ->successMessage('Settings saved');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

The headings and the paragraph carry text the factories escape; the one raw block
is a literal the developer wrote, which is the only kind of string that belongs
in `content()`.

## Html API

```php
->content(string|Closure|null $content)       // rendered UNESCAPED — see How It Works
->getContent(): ?string
Html::make(?string $name = null): static      // the name is optional
Html::divider(): static                       // an <hr>
Html::spacer(string $size = '4'): static      // an empty div at a Tailwind height step
Html::heading(string $text, int $level = 2)   // <h1>–<h3>, escaped, with a matching type scale
Html::paragraph(string $text): static         // a <p>, escaped
```

Visibility and `columnSpan()` are the shared component surface — see
[Common Field API](index.md#common-field-api). Validation, `live()` and defaults
do not apply: `Html` holds no state.

## Related

- [Placeholder](placeholder.md) — text with a label, escaped by default
- [ViewField](view-field.md) — a whole Blade partial instead of a string
- [Alert](alert.md) — a coloured box for a message
- [Section](../../core/schema/layout/section.md) — headings with a card around them

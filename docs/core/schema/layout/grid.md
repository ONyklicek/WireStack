---
order: 10
summary: A multi-column grid for arranging components, per breakpoint — the same layout in a form and in an infolist.
---

# Grid

The plainest way to put two things side by side. `Grid` holds no value of its
own: it takes a column count, lays its children out across those columns, and
gets out of the way. Reach for it when a form is a wall of full-width inputs and
first name / last name obviously belong on one line.

```php
use NyonCode\WireCore\Foundation\Schema\Grid;
```

## How It Works

A grid is a **layout component**: it carries no state and no value, so adding,
removing or reordering one never touches your data. Only fields map to state.
That is why the same `Grid` renders in a form, an infolist and an action modal —
the class owns the configuration, and each package's Blade view owns the chrome.

**The column count resolves in one of two shapes**, both through
`ResponsiveGrid::cols()`:

- **An int** gives a mobile-first reflow: `columns(3)` is one column on phones
  and three from the `md` breakpoint up. It is never three columns on a phone,
  which is the answer most people want and nobody writes.
- **A breakpoint map** says it exactly: `['default' => 1, 'md' => 2, 'xl' => 3]`.
  Keys are `default` (or `''`, or `0`) and then `sm`, `md`, `lg`, `xl`, `2xl`.
  Counts are clamped to 1–12, and an **unknown breakpoint key is ignored
  silently** — `'medium' => 2` produces nothing at all.

The default is **2 columns**. The gap between children is fixed at Tailwind's
`gap-4`; a grid that needs a different rhythm is a [`Flex`](flex.md), which takes
`->gap()`.

Children are rendered in declaration order, and a child whose `visible()` /
`hidden()` condition is false is **left out of the flow entirely** rather than
rendered empty — the grid closes up around it.

**The trap: `columnSpan()` only understands 2, 3, 4 and `full`.** The span map is
a closed `match`, so `->columnSpan(5)` falls through to the default and the field
silently takes one column. If a child must be wider than four, give the grid more
columns rather than the child a bigger span.

**The other trap is Tailwind, not this class.** The grid class is built at
runtime by string concatenation, so Tailwind's scanner cannot see it in your
code. The package writes every possible `grid-cols-*` utility out as literal text
for the scanner to find — which only works if the package views are in your
`content` paths. A grid that renders with the right markup and no columns at all
is nearly always that. See
[Getting Started](../../../start/getting-started.md) for the paths.

## Basic Usage

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name'),
        TextInput::make('last_name'),
    ])
```

`Grid::make()` takes no name, because a layout that holds no state has nothing to
be addressed by. Pass one only if you want it for your own reference.

## Making One Child Wider

A child spans more than its share with `columnSpan()`, and the whole row with
`columnSpanFull()`:

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('first_name'),
        TextInput::make('last_name'),
        Textarea::make('bio')->columnSpanFull(),   // [tl! focus]
    ])
```

`columnSpanFull()` is the one to reach for by default. It means "however many
columns there are, take all of them", so it keeps working when you change the
grid from two columns to three.

## Responsive Columns

When the int reflow is not what you want, say every breakpoint yourself:

```php
Grid::make()
    ->columns([
        'default' => 1,   // phones — one column
        'md' => 2,        // tablets
        'xl' => 3,        // wide desktops
    ])
    ->schema([
        TextInput::make('street'),
        TextInput::make('city'),
        TextInput::make('zip'),
    ])
```

Only the breakpoints you name are emitted, and each one holds until the next.

## Nesting

Grids nest, because a grid is just another component in a schema. This is how a
two-column form gets a row that is itself split three ways:

```php
Grid::make()
    ->columns(2)
    ->schema([
        TextInput::make('name'),
        TextInput::make('email'),
        Grid::make()->columns(3)->columnSpanFull()->schema([   // [tl! focus:start]
            TextInput::make('street'),
            TextInput::make('city'),
            TextInput::make('zip'),
        ]),                                                    // [tl! focus:end]
    ])
```

The inner grid takes `columnSpanFull()` so it occupies the whole row of the outer
one — without it, three fields would be squeezed into one of two columns.

## Extended Example

A registration form in a real Livewire host. The grid is the only layout here;
everything else is an ordinary field:

```php
use Livewire\Component;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class CreateUser extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->model(User::class)
            ->schema([
                Grid::make()                                    // [tl! focus:start]
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('first_name')->required(),
                        TextInput::make('last_name')->required(),
                        TextInput::make('email')->email()->required()->columnSpanFull(),
                        Textarea::make('bio')->columnSpanFull(),
                    ]),                                          // [tl! focus:end]
            ])
            ->successMessage('User created');
    }

    public function save(): void
    {
        $this->form->save();
    }
}
```

```blade
<form wire:submit="save">
    {{ $this->form }}
    <button type="submit">Create</button>
</form>
```

One column on a phone, two from `md`, with e-mail and bio taking the full row on
both.

## Grid API

```php
->columns(int|array $columns)     // 2 (default), or ['default' => 1, 'md' => 2, 'xl' => 3]
                                  // breakpoints: default|sm|md|lg|xl|2xl, counts clamped 1–12
->getColumns(): int|array
```

Everything else a grid understands — `label()`, `visible()`, `hidden()`,
`columnSpan()`, `schema()` — is the shared layout surface every schema component
carries. See [Common Layout API](../overview.md#common-layout-api).

## Related

- [Schema](../overview.md) — the vocabulary this belongs to, and the shared surface
- [Flex](flex.md) — one row that shares its space, when a fixed grid is too rigid
- [Section](section.md) — the same columns, plus a heading and a fold
- [Fieldset](fieldset.md) — the same columns, inside a bordered legend

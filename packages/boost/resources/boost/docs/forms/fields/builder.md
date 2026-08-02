# Builder

Block builder for heterogeneous content: a list of items where each item picks
its own block type and is edited with that block's schema. Where a
[Repeater](repeater.md) repeats *one* schema, a Builder chooses among several —
the shape behind a page builder or a rich content field.

## Basic Usage

```php
use NyonCode\WireForms\Components\Block;
use NyonCode\WireForms\Components\Builder;

Builder::make('content')
    ->blocks([
        Block::make('heading')->icon('star')->schema([
            TextInput::make('text')->rules(['required']),
        ]),
        Block::make('paragraph')->schema([
            Textarea::make('body'),
        ]),
        Block::make('image')->schema([
            FileUpload::make('file'),
            TextInput::make('alt'),
        ]),
    ])
    ->reorderable()
```

The "add" trigger opens a picker listing every declared block; choosing one
appends an item of that type.

## Stored Shape

Each item is stored as its type plus its data:

```php
[
    ['type' => 'heading',   'data' => ['text' => 'Hello']],
    ['type' => 'paragraph', 'data' => ['body' => 'World']],
]
```

Fields bind under `<statePath>.<index>.data`, so a block's schema needs no
knowledge of its position — and a field named `type` inside a block cannot
collide with the item's own discriminator. Cast the attribute to `array` (or
`json`) on the model.

## It Is a Repeater

`Builder` extends `Repeater`, so it shares add/remove/reorder, per-item
reactivity, item limits and the form runtime's treatment of a repeated subtree:

```php
Builder::make('content')
    ->blocks([...])
    ->minItems(1)
    ->maxItems(20)
    ->collapsible()
    ->addButtonLabel('Add block')
```

Only `relationship()` does not apply: mixed block types have no single related
model, so a builder is stored as an array rather than saved through a relation.

## Validation

Block field rules mount under the item's `data` envelope, at
`<path>.*.data.<field>`. Because the resolver validates by wildcard path, blocks
sharing a field *name* share its rules — rules are only as strict as the loosest
block declaring that name. Name fields distinctly where blocks must validate
differently.

## Blocks That No Longer Exist

Stored content outlives the code that declared it. An item whose stored type
names no declared block renders its type as the header and no fields, rather
than making the whole form unrenderable — so the content can still be
recognised, reordered, or removed.

## Declaring a Block

```php
Block::make(string $name)
->label(string|Closure $label)     // header label (auto-generated from name)
->icon(string|Icon $icon)          // shown in the picker and the item header
->schema(array $components)        // the fields this block is edited with
```

A `Block` is a definition, not a rendered surface: placing one directly in a form
schema throws a `FormConfigurationException`.

## Builder API

```php
->blocks(array $blocks)            // the block types this builder can place
->getBlocks(): array
->getBlock(string $name): ?Block
->getItemType(mixed $item): ?string
->table(bool $condition = true)    // throws: see below
// plus the whole Repeater API: addable, deletable, reorderable,
// collapsible, collapsed, minItems, maxItems, addButtonLabel
```

`table()` is the one part of the Repeater API that does not carry over. The
table layout lays a *single* schema out as columns, and a builder's items each
carry a different block's schema, so there is no shared set of columns to head.
Calling it throws a `FormConfigurationException` rather than accepting the flag
and rendering the ordinary builder regardless.

## Related Docs

- [Repeater](repeater.md) — repeat one schema instead of choosing among several
- [Validation](../validation.md)

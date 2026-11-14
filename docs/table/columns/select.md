---
order: 23
summary: An inline dropdown that saves the moment a value is picked — the same combobox as a form's Select, or the browser's own select — with the options and the write path behind it.
---

# SelectColumn

An inline dropdown that saves the moment a value is picked. The cell is the same control
as a form's [`Select`](../../forms/fields/select.md): the shared combobox, with a search box,
several values and a "create option" row when you ask for them — or the browser's own
`<select>` on request.

```php
use NyonCode\WireTable\Columns\SelectColumn;
```

## Basic Usage

```php
SelectColumn::make('status')
    ->options([
        'draft' => 'Draft',
        'review' => 'In Review',
        'published' => 'Published',
        'archived' => 'Archived',
    ])
```

## How the Cell Works

Each cell holds its value in its own editable-cell component, and every pick goes to the
server through `updateTableCell()` — the same guard, validation, optimistic lock and hooks
as any inline edit (see [Inline Editing](editing.md)). The combobox is bound to that
component, not to a Livewire property, so nothing is entangled per row.

Two costs stay off the rows:

- **One list per column.** The option list is drawn once per table, beside the rows. A
  cell carries only its trigger; the first time it opens, it clones the column's list into
  the page under its own row. A page of a hundred rows carries the options once.
- **No view render per row.** The cell is rendered once per column as a skeleton and each
  row's key, value and version are spliced into it.

The server never trusts the list the browser was shown: a value that is not one of the
options is refused, and the cell rolls back with the message under it.

## Relationship Options

```php
SelectColumn::make('category_id')
    ->relationship('category', 'name')   // load options from a related model
```

The related list is the same for every row, so it is fetched **once per render**
and reused — not once per cell. An explicit `->options()` wins over it.

```php
// Pre-seed the list yourself from a known record (rarely needed).
SelectColumn::make('category_id')
    ->relationship('category', 'name')
    ->loadRelationshipOptions($record)
```

## Enum Options

Pass a PHP enum class to expand its cases into `value => label` options. Labels come from
`getLabel()` when the enum implements `Foundation\Contracts\Enum\HasLabel`, otherwise the
case name is headlined. See [Enum & JSON Casts](casts.md) for the contracts.

```php
SelectColumn::make('status')->options(OrderStatus::class)
```

## Searching the List

```php
SelectColumn::make('owner_id')
    ->relationship('owner', 'name')
    ->searchableOptions()
```

`searchableOptions()` puts a search box at the top of the list. It is named apart from
`searchable()`, which on every column means the table's own search reaches the column.

## Several Values

```php
SelectColumn::make('labels')
    ->multiple()
    ->options(['bug' => 'Bug', 'feature' => 'Feature', 'docs' => 'Docs'])
```

A multiple cell holds a list — cast the attribute to `array`. Ticking options writes
nothing yet; the list is written **once, as it closes**, and only if it changed. A
read-only multiple cell shows the labels joined by commas.

## Creating an Option

```php
SelectColumn::make('owner_name')
    ->options(fn () => Owner::query()->pluck('name', 'name')->all())
    ->createOptionForm([
        TextInput::make('name')->required(),
    ])
    ->createOptionUsing(fn (array $data) => Owner::create($data)) // returns the key, or the model
```

The list ends with a "Create option" row. It opens a modal with the form; on submit
`createOptionUsing()` persists the option, and its key is written into the row the list was
opened from — through `updateTableCell()`, like a pick. Every other row of the column can
pick it at once. Nobody can create an option for a cell they could not set: the action
answers to the same permission and `disabled()` rule as the cell.

## Native Control

```php
SelectColumn::make('priority')->options([...])->native()          // the browser's <select> everywhere
SelectColumn::make('priority')->options([...])->nativeOnMobile()  // the browser's on a phone only
SelectColumn::make('status')->options([...])->touchOnMobile()     // a full-height touch list on a phone
```

The same three switches as every select surface, through the shared native-control
concern; the global defaults are `wire-core.mobile.native` and `wire-core.mobile.touch`.
Precedence: `native()` > `touchOnMobile()` > `nativeOnMobile()`. A search box needs the
combobox, so `searchableOptions()` lifts an earlier `native()`.

## Emptying the Cell

```php
SelectColumn::make('assignee_id')->options([...])->selectablePlaceholder()
```

By default the cell cannot be emptied: an attribute that cannot be null would fail the
write. `selectablePlaceholder()` adds the empty row to the list (and to the native select)
and lets the server accept `null`.

## Pasting and Copying

With the [fill handle](fill-handle.md), a block pasted from a spreadsheet is read by label
as well as by key — `Paid` lands as `paid` — and a multiple cell takes a comma-separated
list. Copying rows out puts the labels on the clipboard, which paste back the same way.

## Conditional Disabled

```php
SelectColumn::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->disabled(fn ($record) => $record->is_super_admin)  // can't change super admin
```

## Extended Example

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\SelectColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Task::class)
        ->columns([
            TextColumn::make('title'),
            SelectColumn::make('status')
                ->options(TaskStatus::class)
                ->searchableOptions() // [tl! focus]
                ->touchOnMobile(), // [tl! focus]
            SelectColumn::make('owner_id')
                ->relationship('owner', 'name')
                ->searchableOptions()
                ->createOptionForm([TextInput::make('name')->required()]) // [tl! focus]
                ->createOptionUsing(fn (array $data) => Owner::create($data)), // [tl! focus]
            SelectColumn::make('labels')
                ->multiple() // [tl! focus]
                ->selectablePlaceholder()
                ->options(['bug' => 'Bug', 'feature' => 'Feature', 'docs' => 'Docs']),
            SelectColumn::make('priority')
                ->options(['low' => 'Low', 'high' => 'High'])
                ->native() // [tl! focus]
                ->disabled(fn (Task $record): bool => $record->completed),
        ]);
}
```

## SelectColumn API

```php
->options(array|string|Closure $options): static          // ['value' => 'Label', ...], an enum class, or a closure run once
->getOptions(): array                                      // array<string, string>
->relationship(?string $name, ?string $titleAttribute = null): static // options from a relation, loaded once per render
->loadRelationshipOptions(Model $record): static           // pre-seed that list explicitly
->searchableOptions(bool $searchable = true): static       // a search box in the list
->hasSearchableOptions(): bool
->multiple(bool $multiple = true): static                  // the attribute is a list
->isMultiple(): bool
->selectablePlaceholder(bool $selectable = true): static   // let the cell be emptied
->isPlaceholderSelectable(): bool
->placeholder(string|Closure|null $placeholder): static    // the trigger's empty text
->createOptionForm(array|Closure $schema): static          // a "Create option" row and its modal form
->createOptionUsing(Closure $callback): static             // fn (array $data): key|Model
->createOptionModalHeading(?string $heading): static
->hasCreateOptionForm(): bool
->native(bool $native = true): static                      // the browser's <select>
->nativeOnMobile(bool $condition = true): static
->touchOnMobile(bool $condition = true): static
->disabled(bool|Closure $disabled = true): static
->isDisabled(Model $record): bool
->validate(mixed $value, Model $record): array             // ['valid' => bool, 'errors' => list<string>]
```

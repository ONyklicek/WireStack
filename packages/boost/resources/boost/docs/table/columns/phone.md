---
order: 23
summary: A stored phone number, written the way it is read and linked for a dialler.
---

# PhoneColumn

A phone number a person can read and a device can dial. Reach for it wherever a
column holds E.164 — the shape a database wants, and the shape nobody reads.

```php
use NyonCode\WireTable\Columns\PhoneColumn;
```

## How It Works

**It writes the number with the same grammar the form does.** The prefix is
matched against the shared `Foundation\ValueObjects\DialingCodes` table and the
national part is grouped in threes, which is exactly what
[PhoneInput](../../forms/fields/phone-input.md) shows while it is being edited.
The two surfaces read one table, so a number cannot be spaced one way in a form
and another in the row that lists it.

**The link is the point.** The cell renders as `tel:` + the digits — the spacing
that makes it readable is stripped, because a dialler wants digits — so a click
rings on a phone and opens a softphone on a desktop. Formatting alone is a
`TextColumn::make('phone')->formatStateUsing(…)` away; this column exists for the
link. `notCallable()` keeps the writing and drops it.

**A prefix the table does not carry is left alone.** An unformatted number beats
one grouped against a country it does not belong to — and it is still linked,
because an unknown prefix is not an invalid number.

**A trailing single digit joins the group before it** — `+1 212 555 1234`, not
`+1 212 555 123 4`.

## Basic Usage

```php
PhoneColumn::make('phone')
```

`+420123456789` in the column renders as `+420 123 456 789`, linked as
`tel:+420123456789`.

## Not For Dialling

```php
PhoneColumn::make('fax')
    ->notCallable()
```

## With The Rest Of The Column API

```php
PhoneColumn::make('phone')
    ->label('Mobile')
    ->copyable()             // copy the written number to the clipboard
    ->searchable()
    ->toggleable()
```

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireTable\Columns\PhoneColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Concerns\WithTable;

class ListContacts extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Contact::class)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                PhoneColumn::make('phone')          // [tl! focus:start]
                    ->label('Mobile')
                    ->copyable(),
                PhoneColumn::make('fax')
                    ->notCallable()
                    ->toggleable(isToggledHiddenByDefault: true), // [tl! focus:end]
            ]);
    }
}
```

## PhoneColumn API

The phone surface. Everything else — `->label()`, `->sortable()`,
`->searchable()`, `->copyable()`, `->toggleable()` — is the shared column API,
documented in [Columns](index.md).

```php
->notCallable(bool $condition = true)   // show the number, drop the tel: link
->isCallable(): bool
```

## Related

- [Columns](index.md) — the shared column API every column inherits
- [PhoneInput](../../forms/fields/phone-input.md) — the field that edits the same number
- [TextColumn](text.md) — for a number that needs no link and no grouping

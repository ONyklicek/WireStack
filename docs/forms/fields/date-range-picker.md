---
summary: Two coupled date pickers over two columns, with one-click periods.
---

# DateRangePicker

A period: two dates that belong together, picked as one control and stored in
the two date columns an application already has. Reach for it for a validity, a
booking, a reporting window — anywhere the second date only makes sense
alongside the first.

```php
use NyonCode\WireForms\Components\DateRangePicker;
```

## How It Works

**The pair is composed, not re-implemented.** Each end is an ordinary
[DateTimePicker](date-time-picker.md) bound to its own column, so the calendar,
the typing parser, the native-input fallback, the mobile sheet and the timezone
handling are the ones that field already owns. A second calendar written for
ranges would start drifting from it the week it landed.

**It is a layout, not a field, and that is what makes it persist without a seam
of its own.** The form flattens layout children into its field list, so the two
pickers are ordinary fields to filling, validation and saving — a range lands in
two real date columns, exactly as if the schema had listed two pickers. There is
no array state, no JSON column and no custom save hook.

**The class owns only what neither end can know alone.** The coupling: the end
picker cannot open before the start date, and the start picker cannot pass the
end date. The bound is reactive, so it follows what is picked at the other end,
and it falls back to the range's own `minDate()` / `maxDate()` while that end is
still empty.

**The same constraint is also a rule.** A browser bound is a courtesy — a pasted
value, a stale tab or a disabled picker can still send an end before the start —
so the end carries `after_or_equal:<start path>`, and a period that ends before
it starts fails validation rather than being saved.

**Both ends are live.** The bound closures resolve on the server, so each pick
costs one roundtrip; that is what lets the other end's calendar redraw with the
new limit.

**Presets are resolved server-side.** "This month" is computed in PHP and
rendered as a pair of values, so the browser never has to know what the phrase
means and never disagrees with the server about today's date. Clicking one
writes both ends at once.

## Basic Usage

```php
DateRangePicker::make('validity')
```

Two date pickers over `validity_from` and `validity_to`.

## Naming The Columns

```php
DateRangePicker::make('validity')
    ->from('valid_from')
    ->until('valid_to')
```

The field's own name is a handle for the label and the ids; only these two names
reach the record.

## Bounds

```php
DateRangePicker::make('validity')
    ->minDate('2026-01-01')
    ->maxDate(now()->addYear())
```

Each end reads the range's bound until the other end is picked, and the other
end's value from then on.

## Periods With A Time

```php
DateRangePicker::make('shift')
    ->from('starts_at')
    ->until('ends_at')
    ->withTime()               // both ends pick a date and an hour
```

## One-Click Periods

```php
DateRangePicker::make('period')
    ->presets()                // today, this week, this month, last 30 days, this year
```

```php
DateRangePicker::make('period')
    ->presets([
        'Q1' => ['2026-01-01', '2026-03-31'],
        'Q2' => ['2026-04-01', '2026-06-30'],
    ])
```

A stated set replaces the built-in one. Each value is anything Carbon parses.

## Reaching The Pickers

```php
DateRangePicker::make('validity')
    ->configurePickers(fn (DateTimePicker $picker) => $picker
        ->firstDayOfWeek(1)
        ->disabledDates(['2026-12-24', '2026-12-25']))
```

The callback runs once per end, with that end's picker — the escape hatch for
everything this class does not re-expose.

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class EditContract extends Component
{
    use WithForms;

    public array $data = [];

    public function mount(Contract $contract): void
    {
        $this->form->fill($contract->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Contract::class)
            ->statePath('data')
            ->schema([
                TextInput::make('number')->required(),
                DateRangePicker::make('validity')   // [tl! focus:start]
                    ->label('Validity')
                    ->from('valid_from')
                    ->until('valid_to')
                    ->minDate(today())
                    ->presets()
                    ->required(),                   // [tl! focus:end]
            ]);
    }
}
```

`fill()` reads both columns off the record because they are ordinary fields, and
`save()` writes both back the same way.

## DateRangePicker API

The range surface. Everything a single end can do — the calendar, the native
fallback, the format, the disabled days — belongs to
[DateTimePicker](date-time-picker.md) and is reached through
`configurePickers()`.

```php
->from(string $name)                        // start column — default '{name}_from'
->until(string $name)                       // end column — default '{name}_to'
->fromLabel(?string $label)                 // default: the translated 'From'
->untilLabel(?string $label)                // default: the translated 'To'
->minDate(string|DateTimeInterface|Closure|null $date)  // fn ($get) => … for a reactive bound
->maxDate(string|DateTimeInterface|Closure|null $date)
->displayFormat(?string $format)            // PHP date() tokens; the stored value is unaffected
->withTime(bool $condition = true)          // both ends pick a date and a time
->required(bool $condition = true)          // requires both ends
->presets(array $presets = [])              // ['label' => [from, to], …]; empty = the built-in set
->configurePickers(?Closure $callback)      // fn (DateTimePicker $picker) => …, once per end
```

And what the range answers about itself — the two pickers included, for a
caller that needs to reach one directly:

```php
->getFromName(): string
->getUntilName(): string
->getFromPicker(): DateTimePicker
->getUntilPicker(): DateTimePicker
->hasPresets(): bool
->getPresets(): array                       // ['label' => ['Y-m-d', 'Y-m-d'], …]
```

## Related

- [DateTimePicker](date-time-picker.md) — the field both ends are
- [Form Fields](index.md) — the shared field API
- [Validation](../validation.md) — where the `after_or_equal` rule joins yours

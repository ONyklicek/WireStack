---
summary: One from–to box over a two-month calendar on a desktop, two coupled pickers on a phone — stored in two columns, with one-click periods.
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

**Two views of the same two columns.** From the field's
[mobile breakpoint](../../start/configuration.md#mobile) up the range is one box
reading `01.10.2026 – 31.10.2026` that opens one panel: two months side by side,
the presets beside them, and with `withTime()` a clock for each end. Below the
breakpoint the phone sees the two pickers described next — the controls a phone
already handles well, untouched. Both are in the markup and bound to the same two
state paths, and CSS at the breakpoint shows one, so neither knows which was
used. The hidden pair says `aria-required` rather than `required`, because a
required input the stylesheet hides would stop the form submitting.

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

## Picking A Period

On a desktop the first click on a day starts the period and the second ends it.
Between the two, the span is previewed under the pointer — or under the keyboard
focus, which walks the two months with the same keys as the
[DateTimePicker panel](date-time-picker.md#desktop-panel). A second day before the
first is read as the period clicked backwards and stored forwards; closing the
panel with only one end picked keeps the period as it was.

The box can be typed into too: two dates in the box's format with a dash
between them (`1. 9. 2026 – 15. 9. 2026`, or ` - ` with spaces). Both halves go
through the same day rules as a click, and either half refused puts the last
period back. Emptying the box clears both ends.

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
end's value from then on. In the desktop panel the months and days outside the
bounds are greyed and the arrows stop there.

With a time, the hours of each day can be bounded too —
`minTime()` / `maxTime()` is the [daily time window](date-time-picker.md#daily-time-window),
handed to both ends:

```php
DateRangePicker::make('shift')
    ->withTime()
    ->minDate('2026-10-01')
    ->maxDate('2026-12-31')
    ->minTime('06:00')         // both ends between 06:00 and 18:00, every day
    ->maxTime('18:00')
```

## Periods With A Time

```php
DateRangePicker::make('shift')
    ->from('starts_at')
    ->until('ends_at')
    ->withTime()               // both ends pick a date and an hour
```

On a desktop one [clock dial](time-picker.md#the-clock-dial) sits beside the
calendar with a **From / To** switch above it that reads both times and says
which end the dial sets; the dial snaps to the pickers' `minutesStep()` and
sets the chosen end as it moves, and Enter on the start's minutes moves on to
the end. A fresh start opens at 00:00 and a fresh end on
the last step of its day, so a one-day period covers the day; on a one-day
period the end is never allowed before the start.

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
On a desktop the periods are a list beside the calendar, the one in force
marked; on a phone they are chips above the two pickers.

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
->minTime(string|Closure|null $time)        // daily window, 'H:i' — both ends, every day
->maxTime(string|Closure|null $time)
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
->hasTime(): bool
->isRequired(): bool
->hasPresets(): bool
->getPresets(): array                       // ['label' => ['Y-m-d', 'Y-m-d'], …]
```

## Related

- [DateTimePicker](date-time-picker.md) — the field both ends are
- [Form Fields](index.md) — the shared field API
- [Validation](../validation.md) — where the `after_or_equal` rule joins yours

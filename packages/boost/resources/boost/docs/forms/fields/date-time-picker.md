---
summary: One field for date, month, time or both — the mode decides the calendar, the format and what is stored.
---

# DateTimePicker

Unified date/time picker with date, month, time, and datetime modes.

```php
use NyonCode\WireForms\Components\DateTimePicker;
```

## Modes

```php
// Date only
DateTimePicker::make('birth_date')->asDate()

// DateTime (default)
DateTimePicker::make('event_at')
DateTimePicker::make('event_at')->asDateTime()

// Time only
DateTimePicker::make('alarm')->asTime()

// Month + year only ("YYYY-MM")
DateTimePicker::make('period')->asMonth()

// Explicit mode setter
DateTimePicker::make('x')->mode('date')      // 'date', 'month', 'time', 'datetime'
```

> `asMonth()` always renders the browser-native `<input type="month">` — the custom
> calendar has no month-only grid — so it stays native even if you pass `->native(false)`.

> `asTime()` picks the time on a clock dial on a desktop and with steppers on
> a phone. For a field whose times are
> **slots** — opening hours, appointments — [`TimePicker`](time-picker.md) stores
> the same value but picks it from a list at a fixed interval instead.

## Date Constraints

```php
DateTimePicker::make('start')
    ->minDate('2024-01-01')
    ->maxDate('2025-12-31')
    ->disabledDates(['2024-12-25', '2024-12-31'])
    ->firstDayOfWeek(1)           // Monday
    ->closeOnDateSelection()
```

Bounds take anything readable as a date — a `Carbon`/`DateTimeInterface`, or a
string such as `'2026-07-10'`, `'10.07.2026'`, `'today'` or `'+1 week'` — and are
reshaped for the widget behind the scenes. A bound that cannot be read at all
throws, rather than being silently dropped by the browser.

```php
DateTimePicker::make('start')
    ->minDate(now())              // no past dates
    ->maxDate(now()->addYear())
```

On a `datetime` picker a bound may also carry a time, which then limits the
clock on that boundary day only:

```php
DateTimePicker::make('slot')
    ->minDate('2026-07-10 08:30') // 10 July cannot start before 08:30
    ->maxDate('2026-07-20 17:00') // 20 July cannot run past 17:00
```

A day-granular upper bound covers the whole day: `->maxDate('2026-07-20')`
leaves 20 July selectable up to 23:59.

The bounds and the disabled dates are also **validation rules**. The picker only
draws them, and a drawing can be stepped around — a value typed into the box, or
a phone's own date wheel, which on iOS offers every day regardless of `min` and
`max`. So the field adds its own rule (`Validation\Rules\DateWithinBounds`)
whenever it has anything to hold, and the error names the bound in
`displayFormat()` when there is one. A field with no bounds and no disabled
dates adds no rule.

## Daily Time Window

`minDate()` and `maxDate()` bound the **days**; a time they carry binds on the
first or last day only. Opening hours are a different rule — a time of day that
holds on every day — and that is `minTime()` / `maxTime()`:

```php
DateTimePicker::make('appointment_at')
    ->minDate('2026-10-01')
    ->maxDate('2026-12-31')
    ->minTime('06:00')             // every day from 06:00…
    ->maxTime('18:00')             // …until 18:00
    ->minutesStep(15)
```

How it is resolved:

- **Every day.** The window applies to the clock of a `time` or `datetime`
  picker on each day the calendar allows. A `date` or `month` picker has no
  clock, so the two are ignored there.
- **The stricter wins.** On a boundary day whose `minDate()`/`maxDate()` carries
  a time, the later of the two lower bounds (and the earlier of the upper ones)
  applies. On a `time` picker both always apply, since it has a single day.

```php
DateTimePicker::make('slot')
    ->minDate('2026-10-01 08:30')  // 1 October opens at 08:30, not 06:00
    ->minTime('06:00')
    ->maxTime('18:00')
```

- **Drawn, then held.** In the panel an hour or minute outside the window is
  greyed out, an hour that only straddles it stays pickable and is pulled inside
  (picking 18 under `maxTime('18:00')` lands on 18:00), and a fresh clock opens
  on the window rather than on midnight. A [`TimePicker`](time-picker.md)'s slot
  list and a native slot `<select>` simply stop at its edges. The server repeats
  it through the same `DateWithinBounds` rule as the dates, with its own message
  ("The time in … must not be before 06:00").
- **The touch wheel** of a `datetime` picker does not grey the window yet; the
  server still refuses a value outside it.
- **Format.** `H:i` or `H:i:s` (`'6:00'` is read as `06:00`), a
  `DateTimeInterface` (its clock is taken), or a `$get`-aware Closure. Anything
  else — `'6 pm'`, `'25:00'` — throws when the field renders, rather than
  quietly disabling every hour or none.

## Time Options

```php
DateTimePicker::make('meeting')
    ->withSeconds()
    ->hoursStep(1)
    ->minutesStep(15)
    ->secondsStep(30)
```

## Format

```php
DateTimePicker::make('date')
    ->format('Y-m-d')             // storage format
    ->displayFormat('d.m.Y')      // display format
    ->timezone('Europe/Prague')
```

> `format()` and `timezone()` are opt-in and apply on save. Left unset, the value
> is stored exactly as the widget produced it — so adding them to an existing field
> is a deliberate change of what lands in the column, never a silent one.
> `timezone()` converts in both directions and only for `datetime`: a bare date or
> time is a wall-clock value that a conversion would corrupt.

> `displayFormat()` uses PHP `date()` tokens and only changes what the user sees —
> the stored value is untouched. It is honoured by the custom picker; a native
> input's display format belongs to the browser and the user's locale. Left
> unset, it is `date_format`, `time_format` or `datetime_format` from
> `config/wire-forms.php` for the mode — except on a field with seconds whose
> configured format has none, which shows its value as stored.

## Desktop Panel

From the field's [mobile breakpoint](../../start/configuration.md#mobile) up the
panel is built for a mouse and a keyboard; below it the phone keeps its own
controls — the bottom sheet with steppers, the native input, or the
[touch wheel](#touch-wheel-on-phones) — unchanged. Both are drawn by the same
controller over the same state, so a value picked on one is the value the other
shows.

- **The header** pages a month with its arrows, and its month name and year are
  buttons: the month opens a grid of twelve months, the year a grid of twenty
  years. A date five years back is year → month → day, not sixty clicks. Months
  and years entirely outside `minDate()`/`maxDate()` are greyed, and the arrows
  stop at the bounds.
- **The clock** of a `datetime` sits beside the calendar as the
  [clock dial](time-picker.md#the-clock-dial) `TimePicker` draws — the hours
  face (1–12 outside, 13–23 and 00 inside), then the minutes, snapping to
  `minutesStep()`, with hours and minutes outside the bounds greyed. Here it
  sets the value as it moves, without an OK of its own: the panel's **Done**
  closes it. An `asTime()` picker shows the dial alone.
- **Today / Now** in the footer picks the current day (and, with a clock, the
  current time rounded down to the step), unless the bounds exclude it. **Done**
  closes a `datetime` panel; **Clear** empties the field.
- **The keyboard.** <kbd>↓</kbd> in the box, or the calendar icon, opens the
  panel with focus on the picked day (else today, pulled inside the bounds); a
  click into the box opens it and leaves the caret there for typing.

```text
← →                 previous / next day
↑ ↓                 previous / next week
Home / End          first / last day of the week
PageUp / PageDown   previous / next month (with Shift: year)
Enter / Space       pick the focused day
↑ ↓ on the dial     previous / next hour or minute
Escape              close and return to the box
```

  A day the bounds or `disabledDates()` exclude is stepped over in the
  direction of travel, never landed on.

## Typing

The trigger is a text box, not a button: the value can be typed as well as
picked. What is typed is read back through the same format the box shows —
`displayFormat()` when there is one, the stored shape otherwise — so a field
displaying `9. 3. 2026 14:30` accepts exactly that back.

The parser is loose about everything except the *order* of the parts, which the
format fixes. Under `->displayFormat('j. n. Y H:i')` all of these land on the
same value:

```text
9. 3. 2026 14:30
9.3.2026 14:30
9/3/2026 14:30
9. 3. 26 14:30        a two-digit year is this century
9. 3. 2026            no clock typed, so the time already showing is kept
```

The entry commits on blur and on <kbd>Enter</kbd>; <kbd>Escape</kbd> abandons it.
Anything the parser cannot read — `31. 2. 2026`, an hour past 23, a day that
`minDate()`/`maxDate()`/`disabledDates()` exclude — is refused whole and the
previous value comes back, so a half-read date can never reach the state.
Emptying the box clears the field.

A typed value goes through the same clamp a picked one does: on a boundary day
that carries a time, the clock is pulled inside the bound rather than rejected —
typing `10. 3. 2026 07:00` under `->minDate('2026-03-10 08:30')` stores 08:30.

Close the keyboard route where the value really must come from the widget:

```php
DateTimePicker::make('slot')->typeable(false)
```

> `readOnly()` outranks `typeable()`: it closes the keyboard *and* the panel,
> because the value is not the user's to change by any route. `typeable(false)`
> closes only the keyboard and leaves the calendar working.

> Typing is a custom-picker feature. A native input's keyboard belongs to the
> browser, and the only way to take it away is `readonly` — which would disable
> the browser's own picker along with it — so `typeable(false)` has no effect
> under `->native()`.

## Native Picker

The custom Alpine picker is the default. Opt out to the browser's own control:

```php
DateTimePicker::make('date')
    ->native()                     // use the browser-native picker
    ->native(false)                // back to the custom picker (default)
```

The only exception is [`asMonth()`](#modes), which is always native.

A native time or datetime input carries a `step` taken from the finest unit you
configured — `secondsStep()` (or 1 with `withSeconds()`), else `minutesStep()`,
else `hoursStep()`. That is what makes a native input show seconds at all (the
browser's default step is a minute), and the stride its own validation holds the
value to. `displayFormat()` and `typeable()` belong to the custom picker; a
native input formats and types the way the browser's locale says.

### Native on phones only

The calendar is comfortable with a mouse and cramped on a phone, where the
browser already has a date wheel the user knows. `nativeOnMobile()` keeps the
custom picker from the field's [mobile breakpoint](../../start/configuration.md#mobile)
up and renders the browser's input below it:

```php
DateTimePicker::make('starts_at')
    ->minDate('today')
    ->minutesStep(15)
    ->nativeOnMobile()             // phone: native datetime-local; desktop: the calendar
```

Both are in the markup, bound to the same state, and CSS at the breakpoint shows
one — nothing is decided in the browser, so nothing flashes. The native twin
takes its own id (`{id}-native`) and names itself; `required()` becomes
`aria-required` on both halves, since a required input the stylesheet hides
would block the form on the other screen size. The calendar's bottom sheet is not
rendered, because the native input owns that screen.

`native()` wins over it. Every picker goes native on a phone — the custom one
is the harder control to use there — and what a phone's own control cannot show
is made up for another way:

- **A clock step** (`minutesStep()`, `hoursStep()` on a `time` or `datetime`)
  never goes on `<input type="time">`: to the browser `step` is a validation
  rule, not a wheel, and iOS offers every minute whatever it says. A stepped
  time is a native `<select>` of the slots; a stepped datetime is a native date
  input beside that select, joined into the one state in the browser (a date
  alone writes nothing).
- **Disabled dates and the bounds** cannot be greyed out in a phone's wheel (iOS
  ignores `min`/`max` too), so the server refuses them — see
  [date constraints](#date-constraints).
- **Seconds** — a native time input carries `step` in seconds, which Android
  shows; the iOS wheel has no seconds and saves `:00`.

The app-wide default is `wire-core.mobile.native`; an explicit `native(false)`
opts a field out of it.

### Touch wheel on phones

A phone can get neither the desktop panel nor the browser's control but a touch
wheel: columns in a bottom sheet that coast and snap like the phone's own
picker — hours and minutes for a time, day / month / year for a date, and a day
column beside the clock for a datetime (the iOS shape: "Mon 21/9 · 14 : 30").

```php
TimePicker::make('opens_at')
    ->minDate('08:00')->maxDate('18:00')
    ->touchOnMobile()             // phone: the wheel; desktop: the picker
```

- Each column is an ordinary scroller with CSS scroll-snap, so the momentum and
  the snap are the platform's own physics. The rows tilt like a drum, and on
  Android a turn gives a short vibration (the web has no haptics on iOS).
- The clock columns come from the field's slots (interval and bounds applied);
  a date's years and a datetime's days run between `minDate()` and `maxDate()`.
  A combination the field would refuse is greyed and never kept: past a bound
  the wheels land on the bound itself, 31 February rolls the day back to the
  28th, a disabled day steps to its neighbour, and a clock value that is not a
  slot rolls the other column.
- Nothing is written while the wheel turns: **Done** commits, **Cancel**, the
  backdrop and a swipe down on the grabber leave the value; **Clear** empties
  it. An empty field opens on the next slot from now.
- Each column is a `spinbutton` for screen readers and the keyboard (arrows,
  Home/End); the sheet traps focus and locks the page scroll while it is open.
- Months and weekdays are named in the page's locale (`<html lang>`).
- Every mode but `asMonth()`. It wins over `nativeOnMobile()`; `native()` wins
  over it. It follows the field's `mobileBreakpoint()`, and the app-wide default
  is `wire-core.mobile.touch` — the same switch that gives selects their
  [touch list](select.md#touch-list-on-phones).

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `mode(string)` | string | Set mode: `date`, `month`, `time`, `datetime` |
| `asDate()` | — | Alias for `mode('date')` |
| `asTime()` | — | Alias for `mode('time')` |
| `asMonth()` | — | Alias for `mode('month')`; always native |
| `asDateTime()` | — | Alias for `mode('datetime')` |
| `format(string)` | string | Storage format (Carbon compatible) |
| `displayFormat(string)` | string | Display format shown to the user; default from `wire-forms.date_format` / `time_format` / `datetime_format` |
| `minDate(string\|DateTimeInterface\|Closure)` | string | Earliest selectable date; may carry a time on a `datetime` picker |
| `maxDate(string\|DateTimeInterface\|Closure)` | string | Latest selectable date; a day-granular bound covers the whole day |
| `disabledDates(array\|Closure)` | array | Dates that cannot be selected |
| `firstDayOfWeek(int)` | int | 0=Sunday, 1=Monday |
| `closeOnDateSelection()` | bool | Close picker after a date is selected |
| `withSeconds()` | bool | Show seconds column in time picker |
| `hoursStep(int)` | int | Hour increment step |
| `minutesStep(int)` | int | Minute increment step |
| `secondsStep(int)` | int | Second increment step |
| `minTime(string\|Closure\|null)` | string | Earliest time of day on **every** day, `H:i`/`H:i:s`; `time`/`datetime` only — see [daily time window](#daily-time-window) |
| `maxTime(string\|Closure\|null)` | string | Latest time of day on every day |
| `touchOnMobile(bool $condition = true)` | bool | Touch wheel in a bottom sheet below the mobile breakpoint; not for `asMonth()` (default: `false`) — see [touch wheel on phones](#touch-wheel-on-phones) |
| `timezone(string)` | string | Show the value in this timezone and convert back to the app timezone on save; `datetime` only |
| `native(bool $native = true)` | bool | Use the browser-native control instead of the custom picker (default: `false`) |
| `nativeOnMobile(bool $condition = true)` | bool | Browser-native control below the mobile breakpoint only, the custom picker above it (default: `wire-core.mobile.native`, `false`); a clock step renders a native date + slot `<select>` |
| `typeable(bool\|Closure)` | bool | Let the value be typed into the input as well as picked (default: `true`); custom picker only |
| `disabled(bool\|Closure)` | bool | Disable the picker |
| `readOnly(bool\|Closure)` | bool | Read-only mode — no typing and no panel |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

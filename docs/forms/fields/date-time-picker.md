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

> `asTime()` picks the time with hour/minute steppers. For a field whose times are
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
> input's display format belongs to the browser and the user's locale.

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

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `mode(string)` | string | Set mode: `date`, `month`, `time`, `datetime` |
| `asDate()` | — | Alias for `mode('date')` |
| `asTime()` | — | Alias for `mode('time')` |
| `asMonth()` | — | Alias for `mode('month')`; always native |
| `asDateTime()` | — | Alias for `mode('datetime')` |
| `format(string)` | string | Storage format (Carbon compatible) |
| `displayFormat(string)` | string | Display format shown to the user |
| `minDate(string\|DateTimeInterface\|Closure)` | string | Earliest selectable date; may carry a time on a `datetime` picker |
| `maxDate(string\|DateTimeInterface\|Closure)` | string | Latest selectable date; a day-granular bound covers the whole day |
| `disabledDates(array\|Closure)` | array | Dates that cannot be selected |
| `firstDayOfWeek(int)` | int | 0=Sunday, 1=Monday |
| `closeOnDateSelection()` | bool | Close picker after a date is selected |
| `withSeconds()` | bool | Show seconds column in time picker |
| `hoursStep(int)` | int | Hour increment step |
| `minutesStep(int)` | int | Minute increment step |
| `secondsStep(int)` | int | Second increment step |
| `timezone(string)` | string | Show the value in this timezone and convert back to the app timezone on save; `datetime` only |
| `native(bool $native = true)` | bool | Use the browser-native control instead of the custom picker (default: `false`) |
| `typeable(bool\|Closure)` | bool | Let the value be typed into the input as well as picked (default: `true`); custom picker only |
| `disabled(bool\|Closure)` | bool | Disable the picker |
| `readOnly(bool\|Closure)` | bool | Read-only mode — no typing and no panel |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.

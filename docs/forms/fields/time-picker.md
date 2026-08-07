# TimePicker

Time-only picker. The time is chosen from a **list of slots** at a fixed
interval, rather than by winding steppers.

```php
use NyonCode\WireForms\Components\TimePicker;
```

```php
TimePicker::make('opens_at')
```

Opening the field shows a scrollable list — `00:00`, `00:30`, `01:00`, … — and
clicking one commits it and closes the panel.

## TimePicker vs. DateTimePicker::asTime()

Both store the same value in the same format. They differ only in the panel:

| | Panel |
|---|---|
| `TimePicker::make('x')` | A list of times at `minutesStep()` |
| `DateTimePicker::make('x')->asTime()` | Hour / minute / second steppers |

Pick `TimePicker` when the times are slots — opening hours, appointments,
schedules. Pick [`asTime()`](date-time-picker.md) when any minute of the day is
valid, or when the mode has to vary.

## Interval

The gap between slots is the inherited `minutesStep()`, defaulting to **30**:

```php
TimePicker::make('opens_at')                    // 00:00, 00:30, 01:00 …
TimePicker::make('opens_at')->minutesStep(15)   // 00:00, 00:15, 00:30 …
```

There is no separate `interval()` setter — it is the same concept under the name
it already had. `hoursStep()` and `secondsStep()` are inherited but do nothing
here: a slot list has one stride, not three.

The interval bounds the **list**, not the value. A time can be typed straight
into the trigger, so `08:07` stays reachable at a 30-minute stride — only the
bounds refuse a typed time. See [Typing](date-time-picker.md#typing).

## Bounds

`minDate()` / `maxDate()` read as times and **disable** the slots outside them,
so the range stays visible rather than the list silently shrinking:

```php
TimePicker::make('opens_at')
    ->minDate('08:00')
    ->maxDate('17:00')
```

The panel opens on the current value, or on the first slot the bounds allow — so
a morning-to-evening field does not open at midnight. That holds on a phone too,
where the panel becomes a bottom sheet.

## Everything Else Is DateTimePicker

The value side is entirely inherited, so these behave exactly as documented for
[`DateTimePicker`](date-time-picker.md):

```php
TimePicker::make('opens_at')
    ->withSeconds()               // stored H:i:s; slots still land on :00
    ->displayFormat('H:i')
    ->typeable(false)             // the list only — no typing into the trigger
    ->native()                    // browser's <input type="time">
    ->placeholder('Pick a time')
```

The panel always carries a **Clear** button, so an optional field can be emptied
again without a setter of its own.

The stored value is `H:i`, or `H:i:s` with `withSeconds()`.

> `timezone()` is inherited but does nothing here, exactly as on
> `DateTimePicker::asTime()`: a bare time is a wall-clock value, and converting it
> between zones would corrupt it. It applies to `datetime` only.

> `->native()` hands the whole field to the browser's own time control, so the
> slot list, the interval and the disabled slots all go with it — a native input
> enforces `min`/`max` its own way.

## The Mode Is Locked

`TimePicker` is a time picker permanently. The mode setter — and with it the
inherited `asDate()`, `asMonth()` and `asDateTime()` aliases, which all route
through it — throws `FormConfigurationException`:

```php
TimePicker::make('opens_at')->asDate();       // throws
TimePicker::make('opens_at')->mode('date');   // throws
TimePicker::make('opens_at')->mode('time');   // fine — it already is
```

This is not cosmetic: the panel is a slot list and nothing else, so a field that
reached `date` mode would render a picker with no calendar in it. If the mode has
to vary, that field is a `DateTimePicker`.

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `mode(string)` | string | Locked to `time`; any other mode throws `FormConfigurationException` |

Every other method comes from [DateTimePicker](date-time-picker.md#methods), and
from [Common Field API](index.md#common-field-api) for label, hint, tooltip, and
other shared methods.

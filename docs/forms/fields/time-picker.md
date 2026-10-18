---
summary: A time picked on a clock dial on a desktop and from a list of slots on a phone, at a fixed interval.
---

# TimePicker

Time-only picker for times that come in **slots** at a fixed interval. On a
desktop the time is picked on a **clock dial** — hours, then minutes — and
confirmed with OK; on a phone it is picked from a list of the slots.

```php
use NyonCode\WireForms\Components\TimePicker;
```

```php
TimePicker::make('opens_at')
```

From the field's [mobile breakpoint](../../start/configuration.md#mobile) up,
opening the field shows the dial; below it, a scrollable list — `00:00`,
`00:30`, `01:00`, … — where a tap commits a slot and closes the panel. Both
write the same value.

## The Clock Dial

The desktop panel follows Fiori's time picker:

- **Two big buttons** read the time being chosen (`14 : 30`) and say which
  part the face is setting; a click on either goes back to it.
- **The hours face** has two rings — 1–12 outside, 13–23 and 00 inside. A click
  or a drag picks, and lifting off an hour turns the face to the minutes. The
  **minutes face** snaps to `minutesStep()`; with `withSeconds()` a seconds face
  follows.
- **The rim shows what can be picked.** A dot sits under every number and,
  between the numbers, at each minute the step reaches — sixty places at the
  default step of one minute, none between the numbers at a step of 15. There
  is no hand: the chosen value is a marker on the rim and a filled bubble that
  reads it (`07` too, between two numbers), and the value under the pointer
  gets a lighter one before the click. The face's middle names the part it
  sets.
- **Greyed is not pickable.** An hour wholly outside the bounds or the
  [daily window](date-time-picker.md#daily-time-window), and a minute off the
  step or outside them, are greyed and ignored by the pointer.
- **Nothing is written before OK.** The face edits a draft: **OK** commits it,
  **Cancel** and <kbd>Escape</kbd> leave the value as it was, **Now** sets the
  draft to the current time (rounded down to the step, pulled inside the
  bounds) and **Clear** empties the field. An empty field opens on now, pulled
  inside the bounds, rather than on midnight.
- **The keyboard.** <kbd>↓</kbd> in the box, or the clock icon, opens the panel
  with focus on the face — a slider: the arrows step the part being set,
  skipping what is greyed, and <kbd>Enter</kbd> moves on to the minutes, then
  confirms. A time can always be typed into the box as well.

## TimePicker vs. DateTimePicker::asTime()

Both store the same value in the same format. They differ only in the panel:

| | Panel |
|---|---|
| `TimePicker::make('x')` | A clock dial (a list of times at `minutesStep()` on a phone) |
| `DateTimePicker::make('x')->asTime()` | The same dial, set live without OK (steppers on a phone) |

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

For opening hours the natural words are `minTime()` / `maxTime()` — the
[daily time window](date-time-picker.md#daily-time-window). On a `TimePicker`
they read the same as the bounds, and where both are set the stricter one wins:

```php
TimePicker::make('opens_at')
    ->minTime('06:00')
    ->maxTime('18:00')
```

## Everything Else Is DateTimePicker

The value side is entirely inherited, so these behave exactly as documented for
[`DateTimePicker`](date-time-picker.md):

```php
TimePicker::make('opens_at')
    ->withSeconds()               // stored H:i:s; slots still land on :00
    ->displayFormat('H:i')
    ->typeable(false)             // the list only — no typing into the trigger
    ->native()                    // a native <select> of the slots
    ->placeholder('Pick a time')
```

The panel always carries a **Clear** button, so an optional field can be emptied
again without a setter of its own.

The stored value is `H:i`, or `H:i:s` with `withSeconds()`.

> `timezone()` is inherited but does nothing here, exactly as on
> `DateTimePicker::asTime()`: a bare time is a wall-clock value, and converting it
> between zones would corrupt it. It applies to `datetime` only.

> `->native()` renders the slots as a browser-native `<select>`, not as
> `<input type="time">`: a time input's `step` never reaches a phone's wheel
> (iOS offers every minute), so only a select keeps the value on a slot. It
> lists the slots inside `minDate()`/`maxDate()` at the interval, labelled by
> `displayFormat()`, plus the current value if it sits between two slots.
> `->nativeOnMobile()` does the same below the mobile breakpoint only and keeps
> the slot list above it, whatever the interval or seconds — see
> [native on phones only](date-time-picker.md#native-on-phones-only). The bounds
> are validated on the server either way; a value between slots is not an
> error, because typing one on a desktop is allowed.

> On a phone, `->touchOnMobile()` turns the slot list into a touch wheel — hour
> and minute columns in a bottom sheet — see
> [touch wheel on phones](date-time-picker.md#touch-wheel-on-phones).

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

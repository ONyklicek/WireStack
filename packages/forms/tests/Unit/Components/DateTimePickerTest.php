<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\MessageBag;
use NyonCode\WireCore\Core\State\StateHydrator;
use NyonCode\WireForms\Components\DateTimePicker;

test('default mode is datetime', function () {
    $field = DateTimePicker::make('created_at');

    expect($field->getMode())->toBe('datetime');
});

test('asDate sets mode to date', function () {
    $field = DateTimePicker::make('birth_date')->asDate();

    expect($field->getMode())->toBe('date')
        ->and($field->getNativeInputType())->toBe('date');
});

test('asTime sets mode to time', function () {
    $field = DateTimePicker::make('start')->asTime();

    expect($field->getMode())->toBe('time')
        ->and($field->getNativeInputType())->toBe('time');
});

test('asDateTime sets mode to datetime', function () {
    $field = DateTimePicker::make('event')->asDateTime();

    expect($field->getMode())->toBe('datetime')
        ->and($field->getNativeInputType())->toBe('datetime-local');
});

test('mode can be set directly', function () {
    $field = DateTimePicker::make('ts')->mode('date');

    expect($field->getMode())->toBe('date');
});

test('custom format overrides default', function () {
    $field = DateTimePicker::make('date')->format('d/m/Y');

    expect($field->getFormat())->toBe('d/m/Y');
});

test('state type is a mode-appropriate date string, not a Carbon (regression)', function () {
    expect(DateTimePicker::make('d')->asDate()->getStateType())->toBe('date:Y-m-d')
        ->and(DateTimePicker::make('d')->asTime()->getStateType())->toBe('date:H:i')
        ->and(DateTimePicker::make('d')->asDateTime()->getStateType())->toBe('date:Y-m-d\TH:i');
});

test('hydrator returns a formatted string for date: types (regression)', function () {
    $hydrator = new StateHydrator;

    // Carbon, raw string, and timestamp all reduce to a serializable string.
    expect($hydrator->hydrateValue(Carbon::parse('2026-06-23 14:30:00'), 'date:Y-m-d'))->toBe('2026-06-23')
        ->and($hydrator->hydrateValue('2026-06-23 14:30:00', 'date:Y-m-d\TH:i'))->toBe('2026-06-23T14:30')
        ->and($hydrator->hydrateValue(null, 'date:Y-m-d'))->toBeNull();
});

test('minDate and maxDate', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate('2024-01-01')
        ->maxDate('2024-12-31');

    expect($field->getMinDate())->toBe('2024-01-01')
        ->and($field->getMaxDate())->toBe('2024-12-31');
});

test('minDate and maxDate support closures', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate(fn () => '2024-06-01');

    expect($field->getMinDate())->toBe('2024-06-01');
});

test('bounds are reshaped to the mode the widget compares in', function () {
    // A native <input type="datetime-local"> drops a min it cannot read, and
    // the custom picker compares bounds as plain strings — either way the shape
    // has to match the widget, not whatever the owner found convenient.
    expect(DateTimePicker::make('d')->asDate()->minDate('10.07.2026')->getMinDate())->toBe('2026-07-10')
        ->and(DateTimePicker::make('d')->asMonth()->minDate('2026-07-10')->getMinDate())->toBe('2026-07')
        ->and(DateTimePicker::make('d')->asTime()->minDate('2026-07-10 08:30')->getMinDate())->toBe('08:30')
        ->and(DateTimePicker::make('d')->asTime()->withSeconds()->minDate('08:30:45')->getMinDate())->toBe('08:30:45')
        ->and(DateTimePicker::make('d')->asDateTime()->minDate('2026-07-10')->getMinDate())->toBe('2026-07-10T00:00')
        ->and(DateTimePicker::make('d')->asDateTime()->withSeconds()->minDate('2026-07-10 08:30')->getMinDate())
        ->toBe('2026-07-10T08:30:00');
});

test('bounds accept a Carbon instance', function () {
    $field = DateTimePicker::make('date')
        ->asDate()
        ->minDate(Carbon::parse('2026-07-10 08:30'))
        ->maxDate(fn () => Carbon::parse('2026-07-20 17:00'));

    expect($field->getMinDate())->toBe('2026-07-10')
        ->and($field->getMaxDate())->toBe('2026-07-20');
});

test('bounds accept a relative date', function () {
    Carbon::setTestNow('2026-07-27 12:00:00');

    $field = DateTimePicker::make('date')->asDate()->minDate('today')->maxDate('+2 days');

    expect($field->getMinDate())->toBe('2026-07-27')
        ->and($field->getMaxDate())->toBe('2026-07-29');

    Carbon::setTestNow();
});

test('a day-granular maxDate leaves the whole day selectable on a datetime picker', function () {
    $field = DateTimePicker::make('date')->asDateTime()->maxDate('2026-07-20');

    expect($field->getMaxDate())->toBe('2026-07-20T23:59');
});

test('a maxDate that names a time keeps it', function () {
    $field = DateTimePicker::make('date')->asDateTime()->maxDate('2026-07-20 17:30');

    expect($field->getMaxDate())->toBe('2026-07-20T17:30');
});

test('disabled dates are reshaped to the calendar cells they must match', function () {
    $field = DateTimePicker::make('date')->disabledDates(['10.07.2026', Carbon::parse('2026-07-11')]);

    expect($field->getDisabledDates())->toBe(['2026-07-10', '2026-07-11']);
});

test('native mode', function () {
    $field = DateTimePicker::make('date')->native();

    expect($field->isNative())->toBeTrue();
});

test('defaults to the custom picker', function () {
    expect(DateTimePicker::make('date')->isNative())->toBeFalse();
});

test('native can be turned back off', function () {
    expect(DateTimePicker::make('date')->native()->native(false)->isNative())->toBeFalse();
});

// The custom calendar has no month-only grid, so month mode is the browser's
// <input type="month"> or nothing — it outranks an explicit ->native(false).
test('month mode forces the native control even over an explicit native(false)', function () {
    expect(DateTimePicker::make('date')->asMonth()->isNative())->toBeTrue()
        ->and(DateTimePicker::make('date')->asMonth()->native(false)->isNative())->toBeTrue();
});

test('other modes do not force the native control', function () {
    expect(DateTimePicker::make('date')->asDate()->isNative())->toBeFalse()
        ->and(DateTimePicker::make('date')->asTime()->isNative())->toBeFalse()
        ->and(DateTimePicker::make('date')->asDateTime()->isNative())->toBeFalse();
});

test('firstDayOfWeek defaults to config', function () {
    $field = DateTimePicker::make('date');

    expect($field->getFirstDayOfWeek())->toBeInt();
});

test('withSeconds', function () {
    $field = DateTimePicker::make('time')->asTime()->withSeconds();

    expect($field->hasSeconds())->toBeTrue();
});

test('time steps', function () {
    $field = DateTimePicker::make('time')
        ->hoursStep(2)
        ->minutesStep(15)
        ->secondsStep(30);

    expect($field->getHoursStep())->toBe(2)
        ->and($field->getMinutesStep())->toBe(15)
        ->and($field->getSecondsStep())->toBe(30);
});

test('timezone', function () {
    $field = DateTimePicker::make('event')->timezone('Europe/Prague');

    expect($field->getTimezone())->toBe('Europe/Prague');
});

test('disabled dates', function () {
    $field = DateTimePicker::make('date')->disabledDates(['2024-12-25', '2024-12-26']);

    expect($field->getDisabledDates())->toBe(['2024-12-25', '2024-12-26']);
});

test('close on date selection', function () {
    $field = DateTimePicker::make('date')->closeOnDateSelection();

    expect($field->shouldCloseOnDateSelection())->toBeTrue();
});

function renderPickerView(DateTimePicker $field): string
{
    // The field wrapper reads $errors from the view bag, which only a real
    // request/view context provides.
    return view($field->render()->name(), ['field' => $field])
        ->withErrors(new MessageBag)
        ->render();
}

// Regression: displayFormat() was a dead setter — nothing read it, so the input
// always showed the raw state (2026-03-09T14:05). The custom picker now formats
// it; the native input cannot (the browser owns that, per the user's locale).
test('the custom picker receives displayFormat', function () {
    $field = DateTimePicker::make('event_at')->displayFormat('j. n. Y H:i');

    expect($field->getDisplayFormat())->toBe('j. n. Y H:i')
        ->and(renderPickerView($field))->toContain("displayFormat: 'j. n. Y H:i'");
});

// A browser ignores a min it cannot read, without a word in the console — a
// datetime-local input needs 'Y-m-d\TH:i', a month input 'Y-m'.
test('a native input is given bounds in the shape its type accepts', function () {
    expect(renderPickerView(DateTimePicker::make('d')->native()->minDate('10.07.2026')->maxDate('2026-07-20')))
        ->toContain('min="2026-07-10T00:00"')
        ->toContain('max="2026-07-20T23:59"')
        ->and(renderPickerView(DateTimePicker::make('d')->asDate()->native()->minDate('10.07.2026')))
        ->toContain('min="2026-07-10"')
        ->and(renderPickerView(DateTimePicker::make('d')->asMonth()->minDate('2026-07-10')))
        ->toContain('min="2026-07"');
});

test('the custom picker gets the calendar half and the clock half of each bound', function () {
    $html = renderPickerView(DateTimePicker::make('d')->minDate('2026-07-10 08:30')->maxDate('2026-07-20 17:00'));

    expect($html)->toContain("minDay: '2026-07-10'")
        ->toContain("maxDay: '2026-07-20'")
        ->toContain("minTime: '08:30:00'")
        ->toContain("maxTime: '17:00:00'");
});

test('a date-only picker is given no clock bounds to enforce', function () {
    $html = renderPickerView(DateTimePicker::make('d')->asDate()->minDate('2026-07-10 08:30'));

    expect($html)->toContain("minDay: '2026-07-10'")
        ->toContain('minTime: null');
});

test('a native input is not given a displayFormat to honour', function () {
    $field = DateTimePicker::make('event_at')->native()->displayFormat('j. n. Y H:i');

    expect(renderPickerView($field))->not->toContain('displayFormat:');
});

// closeOnDateSelection() was a dead setter: the picker closed only when the field
// had no time part, and the flag was never read.
test('the picker is told whether to close on date selection', function () {
    expect(renderPickerView(DateTimePicker::make('d')->closeOnDateSelection()))
        ->toContain('closeOnDateSelection: true')
        ->and(renderPickerView(DateTimePicker::make('d')))
        ->toContain('closeOnDateSelection: false');
});

// ─── Format resolution (what the input and the parser agree on) ───────

test('each mode resolves its own storage format', function (string $mode, string $expected) {
    // Pinned rather than relying on whatever the config happens to hold: the
    // suite runs in random order, so a neighbouring test's config() write would
    // otherwise decide the outcome. Month is the one format with no config key.
    config()->set('wire-forms.date_format', 'Y-m-d');
    config()->set('wire-forms.time_format', 'H:i');
    config()->set('wire-forms.datetime_format', 'Y-m-d H:i');

    expect(DateTimePicker::make('at')->mode($mode)->getFormat())->toBe($expected);
})->with([
    'date' => ['date', 'Y-m-d'],
    'month' => ['month', 'Y-m'],
    'time' => ['time', 'H:i'],
    'datetime' => ['datetime', 'Y-m-d H:i'],
]);

test('seconds extend the time formats, in both modes that carry a clock', function () {
    config()->set('wire-forms.time_format', 'H:i');

    expect(DateTimePicker::make('at')->asTime()->withSeconds()->getFormat())->toBe('H:i:s')
        ->and(DateTimePicker::make('at')->asTime()->getFormat())->toBe('H:i')
        // getStateType() is the serialisable shape Livewire round-trips, and it
        // uses the T separator so a native datetime-local input accepts it.
        ->and(DateTimePicker::make('at')->asDateTime()->withSeconds()->getStateType())->toBe('date:Y-m-d\TH:i:s')
        ->and(DateTimePicker::make('at')->asDateTime()->getStateType())->toBe('date:Y-m-d\TH:i')
        ->and(DateTimePicker::make('at')->asTime()->withSeconds()->getStateType())->toBe('date:H:i:s')
        ->and(DateTimePicker::make('at')->asMonth()->getStateType())->toBe('date:Y-m');
});

test('an explicit format wins over the mode default', function () {
    expect(DateTimePicker::make('at')->asDate()->format('d.m.Y')->getFormat())->toBe('d.m.Y');
});

test('the configured formats are used when set', function () {
    config()->set('wire-forms.date_format', 'd/m/Y');
    config()->set('wire-forms.time_format', 'H.i');
    config()->set('wire-forms.datetime_format', 'd/m/Y H.i');

    expect(DateTimePicker::make('at')->asDate()->getFormat())->toBe('d/m/Y')
        ->and(DateTimePicker::make('at')->asTime()->getFormat())->toBe('H.i')
        ->and(DateTimePicker::make('at')->asTime()->withSeconds()->getFormat())->toBe('H.i:s')
        ->and(DateTimePicker::make('at')->asDateTime()->getFormat())->toBe('d/m/Y H.i');
});

// ─── First day of the week ───────────────────────────────────────────

test('the first day of the week comes from config, and can be set per field', function () {
    config()->set('wire-forms.first_day_of_week', 0);   // Sunday

    expect(DateTimePicker::make('at')->getFirstDayOfWeek())->toBe(0)
        // An explicit value wins, and 0 is a real answer rather than "unset".
        ->and(DateTimePicker::make('at')->firstDayOfWeek(1)->getFirstDayOfWeek())->toBe(1)
        ->and(DateTimePicker::make('at')->firstDayOfWeek(6)->getFirstDayOfWeek())->toBe(6)
        // null clears it again, back to the configured value.
        ->and(DateTimePicker::make('at')->firstDayOfWeek(3)->firstDayOfWeek(null)->getFirstDayOfWeek())->toBe(0);
});

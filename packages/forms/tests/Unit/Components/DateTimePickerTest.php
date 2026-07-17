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
        ->minDate('2024-01-01')
        ->maxDate('2024-12-31');

    expect($field->getMinDate())->toBe('2024-01-01')
        ->and($field->getMaxDate())->toBe('2024-12-31');
});

test('minDate and maxDate support closures', function () {
    $field = DateTimePicker::make('date')
        ->minDate(fn () => '2024-06-01');

    expect($field->getMinDate())->toBe('2024-06-01');
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

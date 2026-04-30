<?php

declare(strict_types=1);

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

<?php

declare(strict_types=1);

use Carbon\Carbon;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\DateTimePicker;

test('it names the two columns after itself until it is told otherwise', function () {
    $range = DateRangePicker::make('validity');

    expect($range->getFromName())->toBe('validity_from')
        ->and($range->getUntilName())->toBe('validity_to');

    $named = DateRangePicker::make('validity')->from('valid_from')->until('valid_to');

    expect($named->getFromName())->toBe('valid_from')
        ->and($named->getUntilName())->toBe('valid_to');
});

test('the two ends are ordinary date pickers', function () {
    $range = DateRangePicker::make('validity')->from('valid_from')->until('valid_to');

    [$from, $until] = $range->getSchema();

    expect($from)->toBeInstanceOf(DateTimePicker::class)
        ->and($from->getName())->toBe('valid_from')
        ->and($until->getName())->toBe('valid_to')
        ->and($from->getMode())->toBe('date')
        ->and($from->getLabel())->toBe('From')
        ->and($until->getLabel())->toBe('To');
});

test('withTime picks an hour at each end', function () {
    [$from, $until] = DateRangePicker::make('shift')->withTime()->getSchema();

    expect($from->getMode())->toBe('datetime')
        ->and($until->getMode())->toBe('datetime');
});

test('the schema is built once, so preparation is not thrown away', function () {
    $range = DateRangePicker::make('validity');

    expect($range->getSchema()[0])->toBe($range->getFromPicker())
        ->and($range->getSchema())->toBe($range->getSchema());
});

test('shared configuration reaches both ends', function () {
    $range = DateRangePicker::make('validity')
        ->minDate('2026-01-01')
        ->maxDate('2026-12-31')
        ->displayFormat('d.m.Y')
        ->required()
        ->fromLabel('Valid from')
        ->untilLabel('Valid until');

    [$from, $until] = $range->getSchema();

    expect($from->getDisplayFormat())->toBe('d.m.Y')
        ->and($until->getDisplayFormat())->toBe('d.m.Y')
        ->and($from->isRequired())->toBeTrue()
        ->and($until->isRequired())->toBeTrue()
        ->and($from->getLabel())->toBe('Valid from')
        ->and($until->getLabel())->toBe('Valid until')
        // Nothing is picked yet, so each end still reads the range's own bound.
        ->and($from->getMinDate())->toBe('2026-01-01')
        ->and($until->getMaxDate())->toBe('2026-12-31');
});

test('configurePickers reaches what the range does not re-expose', function () {
    $range = DateRangePicker::make('validity')
        ->configurePickers(fn (DateTimePicker $picker) => $picker->firstDayOfWeek(7));

    [$from, $until] = $range->getSchema();

    expect($from->getFirstDayOfWeek())->toBe(7)
        ->and($until->getFirstDayOfWeek())->toBe(7);
});

test('the end carries the rule that it cannot precede the start', function () {
    $range = DateRangePicker::make('validity')->from('valid_from')->until('valid_to');
    $range->prepareChildren('data');

    [, $until] = $range->getSchema();

    expect($until->getValidationRules())->toContain('after_or_equal:data.valid_from');
});

test('the rule names the start path the form resolved, not the one it was built with', function () {
    // The rule is a Closure because the schema is built before the form
    // prepares its children: a rule captured at build time would name a
    // relative path and compare against nothing.
    $range = DateRangePicker::make('validity');
    [, $until] = $range->getSchema();

    expect($until->getValidationRules())->toContain('after_or_equal:validity_from');

    $range->prepareChildren('data');

    expect($until->getValidationRules())->toContain('after_or_equal:data.validity_from');
});

test('presets are resolved server-side, both ends at once', function () {
    Carbon::setTestNow('2026-09-05');

    $presets = DateRangePicker::make('period')->presets()->getPresets();

    expect($presets)->toHaveKey('This month')
        ->and($presets['Today'])->toBe(['2026-09-05', '2026-09-05'])
        ->and($presets['This month'])->toBe(['2026-09-01', '2026-09-30'])
        ->and($presets['Last 30 days'])->toBe(['2026-08-07', '2026-09-05'])
        ->and($presets['This year'])->toBe(['2026-01-01', '2026-12-31']);

    Carbon::setTestNow();
});

test('a stated preset set replaces the built-in one', function () {
    $range = DateRangePicker::make('period')->presets([
        'Q1' => ['2026-01-01', '2026-03-31'],
    ]);

    expect($range->hasPresets())->toBeTrue()
        ->and($range->getPresets())->toBe(['Q1' => ['2026-01-01', '2026-03-31']]);
});

test('a range offers no presets until it is asked to', function () {
    expect(DateRangePicker::make('period')->hasPresets())->toBeFalse()
        ->and(DateRangePicker::make('period')->getPresets())->toBe([]);
});

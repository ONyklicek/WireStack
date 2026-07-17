<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Services\SummaryFormatter;
use NyonCode\WireTable\Support\SummaryFormat;

/*
 * Formatting used to live in the HasSummary trait next to the SQL aggregation,
 * so exercising it meant building a Column. It now takes a SummaryFormat and
 * nothing else.
 */

beforeEach(function () {
    $this->formatter = new SummaryFormatter;
});

test('a raw number passes through when nothing is configured', function () {
    expect($this->formatter->format(SummaryType::Sum, 1234.5, new SummaryFormat))->toBe(1234.5);
});

test('decimals and separators are applied', function () {
    expect($this->formatter->format(SummaryType::Sum, 1234.5, new SummaryFormat(2, ',', ' ')))
        ->toBe('1 234,50');
});

test('prefix and suffix decorate a formatted number', function () {
    expect($this->formatter->format(SummaryType::Sum, 1234.5, new SummaryFormat(2, ',', ' ', suffix: ' Kč')))
        ->toBe('1 234,50 Kč')
        ->and($this->formatter->format(SummaryType::Sum, 99, new SummaryFormat(prefix: '$')))
        ->toBe('$99');
});

test('counts are left bare — they are not money', function () {
    expect($this->formatter->format(SummaryType::Count, 7, new SummaryFormat(2, ',', ' ', suffix: ' Kč')))
        ->toBe(7)
        ->and($this->formatter->format(SummaryType::DistinctCount, 3, new SummaryFormat(prefix: '$')))
        ->toBe(3);
});

test('a range is already rendered and passes through', function () {
    expect($this->formatter->format(SummaryType::Range, '1 – 9', new SummaryFormat(2, ',', ' ', suffix: ' Kč')))
        ->toBe('1 – 9');
});

test('null and non-numeric values are left alone', function () {
    expect($this->formatter->format(SummaryType::Sum, null, new SummaryFormat(2)))->toBeNull()
        ->and($this->formatter->format(SummaryType::First, 'draft', new SummaryFormat(2)))->toBe('draft');
});

test('a range formats each side but is never decorated', function () {
    // Matches the original: formatRange() called formatNumeric() twice and never
    // decorateNumeric(), so a suffix must not appear on either end.
    expect($this->formatter->range(1000.5, 3000.25, new SummaryFormat(2, ',', ' ', suffix: ' Kč')))
        ->toBe('1 000,50 – 3 000,25');
});

test('numeric() renders an enum-cast value as its label', function () {
    expect($this->formatter->numeric(SfStatus::Draft, new SummaryFormat))->toBe('Draft');
});

test('a closure summary falls back to the generic total label', function () {
    expect($this->formatter->defaultLabel(fn () => 1))->toBe(Trans::get('wire-table::messages.summary_total'))
        ->and($this->formatter->defaultLabel(SummaryType::Sum))->toBe(SummaryType::Sum->label());
});

enum SfStatus: string
{
    case Draft = 'draft';
}

<?php

declare(strict_types=1);

use NyonCode\WireTable\Export\ExportFormat;

// ─── Enum values ─────────────────────────────────────────────────────────────

it('has correct enum values', function () {
    expect(ExportFormat::Csv->value)->toBe('csv')
        ->and(ExportFormat::Excel->value)->toBe('xlsx')
        ->and(ExportFormat::Pdf->value)->toBe('pdf');
});

it('returns correct labels', function () {
    expect(ExportFormat::Csv->label())->toBe('CSV')
        ->and(ExportFormat::Excel->label())->toBe('Excel')
        ->and(ExportFormat::Pdf->label())->toBe('PDF');
});

it('returns correct mime types', function () {
    expect(ExportFormat::Csv->mimeType())->toBe('text/csv')
        ->and(ExportFormat::Excel->mimeType())->toContain('spreadsheetml')
        ->and(ExportFormat::Pdf->mimeType())->toBe('application/pdf');
});

it('returns correct file extensions', function () {
    expect(ExportFormat::Csv->extension())->toBe('csv')
        ->and(ExportFormat::Excel->extension())->toBe('xlsx')
        ->and(ExportFormat::Pdf->extension())->toBe('pdf');
});

it('can be created from string value', function () {
    expect(ExportFormat::from('csv'))->toBe(ExportFormat::Csv)
        ->and(ExportFormat::from('xlsx'))->toBe(ExportFormat::Excel)
        ->and(ExportFormat::from('pdf'))->toBe(ExportFormat::Pdf);
});

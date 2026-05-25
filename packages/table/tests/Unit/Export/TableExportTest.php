<?php

declare(strict_types=1);

use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $export = TableExport::make();

    expect($export)->toBeInstanceOf(TableExport::class);
});

// ─── Defaults ────────────────────────────────────────────────────────────────

it('has correct defaults', function () {
    $export = TableExport::make();

    expect($export->getFileName())->toBe('export')
        ->and($export->hasHeadings())->toBeTrue()
        ->and($export->getFormat())->toBe(ExportFormat::Csv)
        ->and($export->getDelimiter())->toBe(',')
        ->and($export->getEnclosure())->toBe('"')
        ->and($export->getOrientation())->toBe('portrait')
        ->and($export->getPaperSize())->toBe('A4')
        ->and($export->getPdfView())->toBeNull()
        ->and($export->getColumns())->toBeNull()
        ->and($export->getQuery())->toBeNull()
        ->and($export->getModifyQueryCallback())->toBeNull();
});

// ─── Fluent API ──────────────────────────────────────────────────────────────

it('supports fluent chaining', function () {
    $export = TableExport::make()
        ->fileName('orders-2026')
        ->withHeadings(false)
        ->format(ExportFormat::Excel)
        ->delimiter(';')
        ->enclosure("'")
        ->orientation('landscape')
        ->paperSize('Letter')
        ->pdfView('custom.pdf');

    expect($export->getFileName())->toBe('orders-2026')
        ->and($export->hasHeadings())->toBeFalse()
        ->and($export->getFormat())->toBe(ExportFormat::Excel)
        ->and($export->getDelimiter())->toBe(';')
        ->and($export->getEnclosure())->toBe("'")
        ->and($export->getOrientation())->toBe('landscape')
        ->and($export->getPaperSize())->toBe('Letter')
        ->and($export->getPdfView())->toBe('custom.pdf');
});

it('accepts a modifyQueryUsing callback', function () {
    $callback = fn ($q) => $q;
    $export = TableExport::make()->modifyQueryUsing($callback);

    expect($export->getModifyQueryCallback())->toBe($callback);
});

// ─── Validation ──────────────────────────────────────────────────────────────

it('throws when downloading without a query', function () {
    TableExport::make()->download();
})->throws(RuntimeException::class, 'No query defined for export.');

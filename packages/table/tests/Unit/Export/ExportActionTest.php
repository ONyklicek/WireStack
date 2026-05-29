<?php

declare(strict_types=1);

use NyonCode\WireTable\Export\ExportAction;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\TableExport;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via makeExport()', function () {
    $action = ExportAction::makeExport();

    expect($action)->toBeInstanceOf(ExportAction::class)
        ->and($action->getName())->toBe('export')
        ->and($action->getLabel())->toBe('Export')
        ->and($action->getIcon())->toBe('heroicon-o-arrow-down-tray');
});

// ─── Formats ─────────────────────────────────────────────────────────────────

it('has all formats by default', function () {
    $action = ExportAction::makeExport();

    expect($action->getFormats())->toHaveCount(3)
        ->and($action->getFormats())->toContain(ExportFormat::Csv)
        ->and($action->getFormats())->toContain(ExportFormat::Excel)
        ->and($action->getFormats())->toContain(ExportFormat::Pdf);
});

it('can restrict formats', function () {
    $action = ExportAction::makeExport()
        ->formats([ExportFormat::Csv, ExportFormat::Excel]);

    expect($action->getFormats())->toHaveCount(2)
        ->and($action->getFormats())->not->toContain(ExportFormat::Pdf);
});

// ─── Export Config ───────────────────────────────────────────────────────────

it('returns default TableExport config when none set', function () {
    $action = ExportAction::makeExport();

    expect($action->getExportConfig())->toBeInstanceOf(TableExport::class);
});

it('can set custom export config', function () {
    $config = TableExport::make()->fileName('custom-export')->delimiter(';');
    $action = ExportAction::makeExport()->exportConfig($config);

    expect($action->getExportConfig()->getFileName())->toBe('custom-export')
        ->and($action->getExportConfig()->getDelimiter())->toBe(';');
});

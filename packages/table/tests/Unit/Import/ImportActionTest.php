<?php

declare(strict_types=1);

use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\TableImport;

it('can be created via makeImport()', function () {
    $action = ImportAction::makeImport();

    expect($action)->toBeInstanceOf(ImportAction::class)
        ->and($action->getName())->toBe('import')
        ->and($action->getLabel())->toBe('Import')
        ->and($action->getIcon())->toBe('heroicon-o-arrow-up-tray');
});

it('returns a default TableImport config when none is set', function () {
    expect(ImportAction::makeImport()->getImportConfig())->toBeInstanceOf(TableImport::class);
});

it('stores and returns a custom import config', function () {
    $config = TableImport::make();
    $action = ImportAction::makeImport()->importConfig($config);

    expect($action->getImportConfig())->toBe($config);
});

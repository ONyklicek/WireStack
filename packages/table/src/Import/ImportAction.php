<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Export\ExportAction;

/**
 * Header action that imports rows from an uploaded file, using an attached
 * {@see TableImport} configuration. The mirror of
 * {@see ExportAction}; the host resolves this action's
 * config in {@see WithTable::importTable()}.
 */
class ImportAction extends HeaderAction
{
    protected ?TableImport $importConfig = null;

    public static function makeImport(): static
    {
        return static::make('import')
            ->label(Trans::get('wire-table::messages.import_label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray');
    }

    public function importConfig(TableImport $config): static
    {
        $this->importConfig = $config;

        return $this;
    }

    public function getImportConfig(): TableImport
    {
        return $this->importConfig ?? TableImport::make();
    }
}

<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use NyonCode\WireCore\Actions\HeaderAction;

class ExportAction extends HeaderAction
{
    /** @var array<int, ExportFormat> */
    protected array $formats = [ExportFormat::Csv, ExportFormat::Excel, ExportFormat::Pdf];

    protected ?TableExport $exportConfig = null;

    public static function makeExport(): static
    {
        return static::make('export')
            ->label('Export')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray');
    }

    /**
     * @param  array<int, ExportFormat>  $formats
     */
    public function formats(array $formats): static
    {
        $this->formats = $formats;

        return $this;
    }

    /**
     * @return array<int, ExportFormat>
     */
    public function getFormats(): array
    {
        return $this->formats;
    }

    public function exportConfig(TableExport $config): static
    {
        $this->exportConfig = $config;

        return $this;
    }

    public function getExportConfig(): TableExport
    {
        return $this->exportConfig ?? TableExport::make();
    }
}

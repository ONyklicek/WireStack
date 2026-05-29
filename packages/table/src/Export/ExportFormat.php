<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

enum ExportFormat: string
{
    case Csv = 'csv';
    case Excel = 'xlsx';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Excel => 'Excel',
            self::Pdf => 'PDF',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Csv => 'text/csv',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}

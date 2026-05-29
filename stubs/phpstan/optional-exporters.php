<?php

declare(strict_types=1);

namespace OpenSpout\Common\Entity;

class Cell
{
    public static function fromValue(mixed $value): self
    {
        return new self;
    }
}

class Row
{
    /**
     * @param  array<int, Cell>  $cells
     */
    public function __construct(array $cells) {}
}

namespace OpenSpout\Writer\XLSX;

use OpenSpout\Common\Entity\Row;

class Writer
{
    public function openToFile(string $outputFilePath): void {}

    public function addRow(Row $row): void {}

    public function close(): void {}
}

namespace Barryvdh\DomPDF;

class PDF
{
    public function setPaper(string $paper, string $orientation = 'portrait'): self
    {
        return $this;
    }

    public function output(): string
    {
        return '';
    }
}

namespace Barryvdh\DomPDF\Facade;

use Barryvdh\DomPDF\PDF;

class Pdf
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function loadView(string $view, array $data = []): PDF
    {
        return new PDF;
    }
}

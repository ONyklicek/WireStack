<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\ExcelExporter;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\PdfExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OptionalExporterRecord extends Model
{
    protected $table = 'optional_exporter_records';

    protected $guarded = [];
}

class UnavailableExcelExporter extends ExcelExporter
{
    public static function isAvailable(): bool
    {
        return false;
    }
}

class UnavailablePdfExporter extends PdfExporter
{
    public static function isAvailable(): bool
    {
        return false;
    }
}

beforeEach(function () {
    Schema::create('optional_exporter_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    OptionalExporterRecord::create(['name' => 'Alice']);
});

afterEach(function () {
    Schema::dropIfExists('optional_exporter_records');
});

it('falls back to csv when OpenSpout is unavailable', function () {
    $response = (new UnavailableExcelExporter)
        ->export(OptionalExporterRecord::query(), [TextColumn::make('name')->label('Name')], 'report.xlsx');

    $output = captureOptionalExporterResponse($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('report.csv')
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Alice');
});

it('falls back to csv when DomPDF is unavailable', function () {
    $response = (new UnavailablePdfExporter)
        ->export(OptionalExporterRecord::query(), [TextColumn::make('name')->label('Name')], 'report.pdf');

    $output = captureOptionalExporterResponse($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('report.csv')
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Alice');
});

it('creates an xlsx response when OpenSpout is available', function () {
    if (! ExcelExporter::isAvailable()) {
        $this->markTestSkipped('OpenSpout is not installed.');
    }

    $response = (new ExcelExporter)
        ->export(OptionalExporterRecord::query(), [TextColumn::make('name')->label('Name')], 'report.xlsx');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe(ExportFormat::Excel->mimeType())
        ->and($response->headers->get('Content-Disposition'))->toContain('report.xlsx');
});

it('creates a pdf response when DomPDF is available', function () {
    if (! PdfExporter::isAvailable()) {
        $this->markTestSkipped('DomPDF is not installed.');
    }

    $response = (new PdfExporter)
        ->export(OptionalExporterRecord::query(), [TextColumn::make('name')->label('Name')], 'report.pdf');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe(ExportFormat::Pdf->mimeType())
        ->and($response->headers->get('Content-Disposition'))->toContain('report.pdf');
});

function captureOptionalExporterResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

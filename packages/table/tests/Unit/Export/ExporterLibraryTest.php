<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\CsvExporter;
use NyonCode\WireTable\Export\ExcelExporter;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\PdfExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The half of the exporters that only runs when the optional library is really
 * there — `writeTo()` past the `isAvailable()` guard, and the response that
 * streams it.
 *
 * It had never run. `openspout` and `dompdf` were named in the docs and in no
 * manifest, so every existing test either faked the library away or skipped
 * itself, and "real XLSX files" was a headline feature whose library path no CI
 * run had ever entered. Both are in the monorepo's require-dev for this file,
 * which is why the guards below are expectations rather than skips: a run
 * without the libraries must fail here, not quietly pass.
 *
 * The fallback half — what happens when they are absent — stays in
 * OptionalExporterTest.php, which fakes `isAvailable()` off and therefore keeps
 * working either way.
 */
class ExporterLibraryRecord extends Model
{
    protected $table = 'exporter_library_records';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('exporter_library_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('note')->nullable();
        $table->timestamps();
    });

    ExporterLibraryRecord::create(['name' => 'Ada', 'note' => 'first']);
    ExporterLibraryRecord::create(['name' => 'Grace', 'note' => 'second']);
});

afterEach(function () {
    Schema::dropIfExists('exporter_library_records');
});

// ─── OpenSpout ───────────────────────────────────────────────────────────────

it('has the optional libraries installed to test against', function () {
    expect(ExcelExporter::isAvailable())->toBeTrue()
        ->and(PdfExporter::isAvailable())->toBeTrue();
});

it('writes a real xlsx workbook rather than a csv wearing the extension', function () {
    $path = exporterLibraryTempPath('xlsx');

    (new ExcelExporter)->writeTo($path, ExporterLibraryRecord::query(), exporterLibraryColumns());

    // A .xlsx is a zip. Reading it as one is what distinguishes a real workbook
    // from the CSV the fallback would have written under the same name — the
    // exact lie `extension()` exists to prevent.
    expect(substr((string) file_get_contents($path), 0, 2))->toBe('PK');

    $contents = exporterLibraryUnzip($path);

    expect($contents)->toHaveKey('xl/worksheets/sheet1.xml');

    // The sheet, not the archive. OpenSpout writes XLSX cells as `inlineStr`, so
    // `xl/sharedStrings.xml` is emitted empty and the values are only here — and
    // searching the whole zip would match `PartName` in `[Content_Types].xml` for
    // any assertion involving the word "name".
    $sheet = $contents['xl/worksheets/sheet1.xml'];

    expect($sheet)->toContain('<t>Name</t>')
        ->and($sheet)->toContain('<t>Ada</t>')
        ->and($sheet)->toContain('<t>Grace</t>');
});

it('reports the extension it is actually going to write', function () {
    expect((new ExcelExporter)->extension())->toBe('xlsx')
        ->and((new PdfExporter)->extension())->toBe('pdf');
});

it('drops the headings from the workbook when asked to', function () {
    $path = exporterLibraryTempPath('xlsx');

    (new ExcelExporter(withHeadings: false))
        ->writeTo($path, ExporterLibraryRecord::query(), exporterLibraryColumns());

    $sheet = exporterLibrarySheet($path);

    expect($sheet)->toContain('<t>Ada</t>')
        ->and($sheet)->not->toContain('<t>Name</t>');
});

it('appends the summary rows after the data rows in the workbook', function () {
    $path = exporterLibraryTempPath('xlsx');

    (new ExcelExporter)->writeTo(
        $path,
        ExporterLibraryRecord::query(),
        exporterLibraryColumns(),
        [['Total', '2 records']],
    );

    $sheet = exporterLibrarySheet($path);

    expect($sheet)->toContain('<t>Total</t>')
        ->and($sheet)->toContain('<t>2 records</t>');
});

it('writes a leading = as text, so the workbook does not open as a formula', function () {
    ExporterLibraryRecord::create(['name' => '=1+1', 'note' => 'injected']);

    $path = exporterLibraryTempPath('xlsx');

    (new ExcelExporter)->writeTo($path, ExporterLibraryRecord::query(), exporterLibraryColumns());

    $sheet = exporterLibrarySheet($path);

    // The escape is the leading apostrophe the CSV writer uses too; the sheet
    // stores it XML-escaped, hence either spelling. What must never reach the
    // file is a cell holding the bare `=1+1` a spreadsheet evaluates on open.
    expect($sheet)->toMatch('/<t>(?:&#0?39;|\')=1\+1<\/t>/')
        ->and($sheet)->not->toContain('<t>=1+1</t>');
});

it('streams the workbook as the download, without falling back to csv', function () {
    $response = (new ExcelExporter)
        ->export(ExporterLibraryRecord::query(), exporterLibraryColumns(), 'people.xlsx');

    $output = exporterLibraryCapture($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Type'))->toBe(ExportFormat::Excel->mimeType())
        ->and($response->headers->get('Content-Disposition'))->toContain('people.xlsx')
        ->and(substr($output, 0, 2))->toBe('PK');
});

// ─── DomPDF ──────────────────────────────────────────────────────────────────

it('writes a real pdf document', function () {
    $path = exporterLibraryTempPath('pdf');

    (new PdfExporter)->writeTo($path, ExporterLibraryRecord::query(), exporterLibraryColumns());

    $bytes = (string) file_get_contents($path);

    expect(substr($bytes, 0, 5))->toBe('%PDF-')
        ->and($bytes)->toContain('%%EOF');
});

it('streams the pdf as the download, without falling back to csv', function () {
    $response = (new PdfExporter)
        ->export(ExporterLibraryRecord::query(), exporterLibraryColumns(), 'people.pdf');

    $output = exporterLibraryCapture($response);

    expect($response->headers->get('Content-Type'))->toBe(ExportFormat::Pdf->mimeType())
        ->and($response->headers->get('Content-Disposition'))->toContain('people.pdf')
        ->and(substr($output, 0, 5))->toBe('%PDF-');
});

it('renders the pdf through a view of the caller\'s choosing', function () {
    // A real second view, not the packaged one under another name: the branch
    // under test is `$this->view ?? 'wire-table::export.pdf'`, and passing the
    // default back in would have exercised the fallback either way.
    $directory = sys_get_temp_dir().'/wire-export-views-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents(
        $directory.'/custom.blade.php',
        '<h1>Ledger</h1>@foreach ($rows as $row)<p>{{ $row[0] }}</p>@endforeach',
    );
    View::addNamespace('wire-export-test', $directory);

    $path = exporterLibraryTempPath('pdf');

    (new PdfExporter(view: 'wire-export-test::custom'))
        ->writeTo($path, ExporterLibraryRecord::query(), exporterLibraryColumns(), [['Total', '2']]);

    expect(substr((string) file_get_contents($path), 0, 5))->toBe('%PDF-');

    // DomPDF compresses its content streams, so the words are not greppable in
    // the bytes. What the file *can* prove is that the custom view was the one
    // asked for — Blade compiled it, and a missing view would have thrown.
    expect(view('wire-export-test::custom', ['rows' => [['Ada']]])->render())
        ->toContain('Ledger');

    array_map('unlink', glob($directory.'/*') ?: []);
    rmdir($directory);
});

// ─── A write that cannot happen says so ──────────────────────────────────────

it('refuses a path it cannot write the workbook to, in the contract\'s own words', function () {
    // OpenSpout raises its own IOException here. Left alone it would make this
    // exporter the one that answers differently from the other two, and a caller
    // would have to know which optional library is underneath to catch it.
    expect(fn () => (new ExcelExporter)->writeTo(
        '/nonexistent-directory-for-exports/report.xlsx',
        ExporterLibraryRecord::query(),
        exporterLibraryColumns(),
    ))->toThrow(RuntimeException::class, 'report.xlsx');
});

it('refuses a path it cannot write the pdf to, instead of writing nothing', function () {
    expect(fn () => (new PdfExporter)->writeTo(
        '/nonexistent-directory-for-exports/report.pdf',
        ExporterLibraryRecord::query(),
        exporterLibraryColumns(),
    ))->toThrow(RuntimeException::class, 'Could not open');
});

it('refuses a path it cannot write the csv to, instead of returning silently', function () {
    // Before this, the guard could not be reached at all: the `fopen` warning
    // became an ErrorException about a stream, so the caller was told which
    // function failed and never which export did.
    expect(fn () => (new CsvExporter)->writeTo(
        '/nonexistent-directory-for-exports/report.csv',
        ExporterLibraryRecord::query(),
        exporterLibraryColumns(),
    ))->toThrow(RuntimeException::class, 'report.csv');
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @return array<int, TextColumn>
 */
function exporterLibraryColumns(): array
{
    return [TextColumn::make('name')->label('Name')];
}

function exporterLibraryTempPath(string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'wire-export-test').'.'.$extension;

    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/**
 * @return array<string, string>
 */
function exporterLibraryUnzip(string $path): array
{
    $zip = new ZipArchive;

    expect($zip->open($path))->toBeTrue();

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $entries[$name] = (string) $zip->getFromIndex($i);
    }

    $zip->close();

    return $entries;
}

function exporterLibrarySheet(string $path): string
{
    return exporterLibraryUnzip($path)['xl/worksheets/sheet1.xml'];
}

function exporterLibraryCapture(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

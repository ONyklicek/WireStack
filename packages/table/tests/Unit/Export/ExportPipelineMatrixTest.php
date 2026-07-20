<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\CsvExporter;
use NyonCode\WireTable\Export\ExcelExporter;
use NyonCode\WireTable\Export\PdfExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Seam matrix: every export format over the full export pipeline, including the
 * two conditions that broke it — a relation-sort JOIN (unqualified `id` in
 * chunkById) and appended summary rows. CSV asserts exact content; Excel and PDF
 * fall back to CSV when their optional dependency is absent, so the matrix
 * asserts they stream without throwing regardless.
 */

class EmxCompany extends Model
{
    protected $table = 'emx_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class EmxUser extends Model
{
    protected $table = 'emx_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(EmxCompany::class, 'company_id');
    }
}

beforeEach(function () {
    Schema::create('emx_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('emx_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->foreignId('company_id');
    });

    EmxCompany::insert([['id' => 1, 'name' => 'Zeta'], ['id' => 2, 'name' => 'Alpha']]);
    EmxUser::insert([
        ['name' => 'Bob', 'company_id' => 1],   // Zeta
        ['name' => 'Ann', 'company_id' => 2],   // Alpha
    ]);
});

afterEach(function () {
    Schema::dropIfExists('emx_users');
    Schema::dropIfExists('emx_companies');
});

/** A relation-sorted export query: LEFT JOIN + order by the joined column. */
function emxJoinedQuery(): Builder
{
    return EmxUser::query()
        ->select('emx_users.*')
        ->leftJoin('emx_companies as emx_users_company', 'emx_users_company.id', '=', 'emx_users.company_id')
        ->orderBy('emx_users_company.name');
}

function emxCapture(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

// ─── Format matrix: every exporter streams over a joined query + summaries ────

it('exports every format over a relation-joined query with summary rows', function (Closure $makeExporter) {
    $columns = [TextColumn::make('name')->label('Name')];
    $summaryRows = [['Total', '2']];

    $output = emxCapture($makeExporter()->export(emxJoinedQuery(), $columns, 'users.dat', $summaryRows));

    // The point: it produced output instead of throwing "ambiguous column: id".
    expect($output)->toBeString()->not->toBe('');
})->with([
    'csv' => fn () => new CsvExporter,
    'excel' => fn () => new ExcelExporter,
    'pdf' => fn () => new PdfExporter,
]);

// ─── CSV content correctness (readable format) ───────────────────────────────

it('writes CSV rows in relation-sort order with the summary row appended', function () {
    $columns = [TextColumn::make('name')->label('Name')];
    $summaryRows = [['Total', '2']];

    $output = emxCapture((new CsvExporter)->export(emxJoinedQuery(), $columns, 'users.csv', $summaryRows));

    // Ordered by company name (Alpha before Zeta) → Ann then Bob.
    expect($output)->toContain('Ann')
        ->and($output)->toContain('Bob')
        ->and(strpos($output, 'Ann'))->toBeLessThan(strpos($output, 'Bob'))
        // Summary row is appended after the data.
        ->and($output)->toContain('Total')
        ->and(strpos($output, 'Total'))->toBeGreaterThan(strpos($output, 'Bob'));
});

it('exports an empty result set without error', function () {
    EmxUser::query()->delete();
    $columns = [TextColumn::make('name')->label('Name')];

    $output = emxCapture((new CsvExporter)->export(emxJoinedQuery(), $columns, 'users.csv'));

    // Header only, no data rows, no throw.
    expect($output)->toContain('Name');
});

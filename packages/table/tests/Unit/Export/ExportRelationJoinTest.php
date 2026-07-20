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
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Regression: exports run `$query->chunkById(1000, ...)`, whose default cursor is
 * the unqualified `id`. When the table is sorted by a relation column the export
 * query carries a LEFT JOIN, so `id` (present on both tables) is ambiguous and
 * the whole export throws. The exporters must pass the qualified key.
 */

class ExpUser extends Model
{
    protected $table = 'exp_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(ExpCompany::class, 'company_id');
    }
}

class ExpCompany extends Model
{
    protected $table = 'exp_companies';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('exp_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('exp_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->foreignId('company_id');
    });

    ExpCompany::insert([['name' => 'Zeta'], ['name' => 'Alpha']]);
    ExpUser::insert([
        ['name' => 'Bob', 'company_id' => 1],
        ['name' => 'Ann', 'company_id' => 2],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('exp_users');
    Schema::dropIfExists('exp_companies');
});

/** A relation-sorted export query: LEFT JOIN + order by the joined column. */
function expJoinedQuery(): Builder
{
    return ExpUser::query()
        ->select('exp_users.*')
        ->leftJoin('exp_companies as exp_users_company', 'exp_users_company.id', '=', 'exp_users.company_id')
        ->orderBy('exp_users_company.name');
}

function expCapture(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

it('exports CSV over a relation-joined query without an ambiguous id', function () {
    $columns = [TextColumn::make('name')->label('Name')];

    $output = expCapture((new CsvExporter)->export(expJoinedQuery(), $columns, 'users.csv'));

    // Ordered by company name (Alpha before Zeta) → Ann then Bob; no exception.
    expect($output)->toContain('Ann')
        ->and($output)->toContain('Bob')
        ->and(strpos($output, 'Ann'))->toBeLessThan(strpos($output, 'Bob'));
});

it('exports Excel over a relation-joined query without an ambiguous id', function () {
    $columns = [TextColumn::make('name')->label('Name')];

    $response = (new ExcelExporter)->export(expJoinedQuery(), $columns, 'users.xlsx');

    // The streamed generator runs chunkById; capturing it must not throw.
    expect(expCapture($response))->toBeString()
        ->and($response)->toBeInstanceOf(StreamedResponse::class);
});

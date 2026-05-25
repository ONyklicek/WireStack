<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableExportDownloadRecord extends Model
{
    protected $table = 'table_export_download_records';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('table_export_download_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('secret');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    TableExportDownloadRecord::create(['name' => 'Alice', 'secret' => 'internal-a', 'active' => true]);
    TableExportDownloadRecord::create(['name' => 'Bob', 'secret' => 'internal-b', 'active' => false]);
});

afterEach(function () {
    Schema::dropIfExists('table_export_download_records');
});

it('downloads a successful export with modified query and viewable columns only', function () {
    $response = TableExport::make()
        ->fileName('active-records')
        ->query(TableExportDownloadRecord::query())
        ->columns([
            TextColumn::make('name')->label('Name'),
            TextColumn::make('secret')->label('Secret')->authorize('view-secret'),
        ])
        ->modifyQueryUsing(fn ($query) => $query->where('active', true))
        ->download();

    $output = captureTableExportDownloadResponse($response);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('Content-Disposition'))->toContain('active-records.csv')
        ->and($output)->toContain('Name')
        ->and($output)->toContain('Alice')
        ->and($output)->not->toContain('Bob')
        ->and($output)->not->toContain('Secret')
        ->and($output)->not->toContain('internal-a');
});

function captureTableExportDownloadResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

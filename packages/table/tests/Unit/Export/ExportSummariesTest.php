<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\TableExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSummariesOrder extends Model
{
    protected $table = 'export_summaries_orders';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(ExportSummariesItem::class, 'order_id');
    }
}

class ExportSummariesItem extends Model
{
    protected $table = 'export_summaries_items';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('export_summaries_orders', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->integer('total');
        $table->timestamps();
    });

    Schema::create('export_summaries_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id');
        $table->integer('amount');
        $table->timestamps();
    });

    $o1 = ExportSummariesOrder::create(['number' => 'ORD-1', 'total' => 100]);
    $o2 = ExportSummariesOrder::create(['number' => 'ORD-2', 'total' => 250]);

    ExportSummariesItem::create(['order_id' => $o1->id, 'amount' => 60]);
    ExportSummariesItem::create(['order_id' => $o1->id, 'amount' => 40]);
    ExportSummariesItem::create(['order_id' => $o2->id, 'amount' => 250]);
});

afterEach(function () {
    Schema::dropIfExists('export_summaries_items');
    Schema::dropIfExists('export_summaries_orders');
});

function captureExportSummariesResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

it('appends query-scoped summary rows to a csv export', function () {
    $response = TableExport::make()
        ->fileName('orders')
        ->query(ExportSummariesOrder::query())
        ->columns([
            TextColumn::make('number')->label('Number'),
            TextColumn::make('total')->label('Total')->summarizeSum('Grand total'),
        ])
        ->download();

    $output = captureExportSummariesResponse($response);

    expect($output)->toContain('ORD-1')
        ->and($output)->toContain('Grand total: 350');
});

it('omits summary rows when withSummaries is disabled', function () {
    $response = TableExport::make()
        ->fileName('orders')
        ->withSummaries(false)
        ->query(ExportSummariesOrder::query())
        ->columns([
            TextColumn::make('total')->summarizeSum('Grand total'),
        ])
        ->download();

    $output = captureExportSummariesResponse($response);

    expect($output)->not->toContain('Grand total');
});

it('renders one summary row per stacked summary', function () {
    $response = TableExport::make()
        ->fileName('orders')
        ->query(ExportSummariesOrder::query())
        ->columns([
            TextColumn::make('total')
                ->summarizeSum('Sum')
                ->summarizeMax('Largest'),
        ])
        ->download();

    $output = captureExportSummariesResponse($response);

    expect($output)->toContain('Sum: 350')
        ->and($output)->toContain('Largest: 250');
});

it('exports rollup column values and their grand total', function () {
    $response = TableExport::make()
        ->fileName('orders')
        ->query(ExportSummariesOrder::query()->withSum('items', 'amount'))
        ->columns([
            TextColumn::make('number')->label('Number'),
            TextColumn::make('items_total')
                ->label('Items total')
                ->sums('items', 'amount')
                ->summarizeSum('Celkem'),
        ])
        ->download();

    $output = captureExportSummariesResponse($response);

    // Per-row rollup values come from the withSum alias…
    expect($output)->toContain('100')
        ->and($output)->toContain('250')
        // …and the grand total aggregates the alias in SQL.
        ->and($output)->toContain('Celkem: 350');
});

it('excludes page and selection scoped summaries from exports', function () {
    $response = TableExport::make()
        ->fileName('orders')
        ->query(ExportSummariesOrder::query())
        ->columns([
            TextColumn::make('total')->summarizeSum('Page sum', scope: 'page'),
        ])
        ->download();

    $output = captureExportSummariesResponse($response);

    expect($output)->not->toContain('Page sum');
});

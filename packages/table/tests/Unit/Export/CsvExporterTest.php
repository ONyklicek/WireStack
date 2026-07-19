<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Export\CsvExporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

// ─── CSV Generation ──────────────────────────────────────────────────────────

it('creates a streamed response', function () {
    $query = createMockQuery([]);
    $columns = [
        TextColumn::make('name'),
        TextColumn::make('email'),
    ];

    $exporter = new CsvExporter;
    $response = $exporter->export($query, $columns, 'test.csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('test.csv');
});

it('includes headings by default', function () {
    $records = [
        createMockRecord(['name' => 'John', 'email' => 'john@example.com']),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('name')->label('Name'),
        TextColumn::make('email')->label('Email'),
    ];

    $exporter = new CsvExporter;
    $output = captureStreamedResponse($exporter->export($query, $columns, 'test.csv'));

    // Should contain BOM + header + data row
    expect($output)->toContain('Name')
        ->and($output)->toContain('Email')
        ->and($output)->toContain('John')
        ->and($output)->toContain('john@example.com');
});

it('can exclude headings', function () {
    $records = [
        createMockRecord(['name' => 'John', 'email' => 'john@example.com']),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('name')->label('Name'),
        TextColumn::make('email')->label('Email'),
    ];

    $exporter = new CsvExporter(withHeadings: false);
    $output = captureStreamedResponse($exporter->export($query, $columns, 'test.csv'));

    // Should NOT contain the header row labels separately (they won't appear as a header line)
    $lines = array_filter(explode("\n", trim($output)));
    // First real line should be data, not header
    expect(count($lines))->toBe(1);
});

it('uses custom delimiter and enclosure', function () {
    $records = [
        createMockRecord(['name' => 'John Doe', 'city' => 'New York']),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('name'),
        TextColumn::make('city'),
    ];

    $exporter = new CsvExporter(delimiter: ';', enclosure: "'");
    $output = captureStreamedResponse($exporter->export($query, $columns, 'test.csv'));

    expect($output)->toContain(';');
});

it('handles null values as empty strings', function () {
    $records = [
        createMockRecord(['name' => 'John', 'email' => null]),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('name'),
        TextColumn::make('email'),
    ];

    $exporter = new CsvExporter;
    $output = captureStreamedResponse($exporter->export($query, $columns, 'test.csv'));

    expect($output)->toContain('John');
});

it('handles boolean values', function () {
    $records = [
        createMockRecord(['name' => 'John', 'active' => true]),
        createMockRecord(['name' => 'Jane', 'active' => false]),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('name'),
        TextColumn::make('active'),
    ];

    $exporter = new CsvExporter;
    $output = captureStreamedResponse($exporter->export($query, $columns, 'test.csv'));

    expect($output)->toContain('1')
        ->and($output)->toContain('0');
});

it('neutralises spreadsheet formula injection but leaves numbers intact', function () {
    // Regression M12: a value a spreadsheet would evaluate (leading =, +, @, or a
    // non-numeric leading -) is prefixed with a single quote; a genuine negative
    // number is left alone so numeric exports stay numeric.
    $records = [
        createMockRecord(['formula' => '=HYPERLINK("http://evil","x")', 'amount' => '-42']),
        createMockRecord(['formula' => '@SUM(A1)', 'amount' => '+dangerous']),
    ];
    $query = createMockQuery($records);
    $columns = [
        TextColumn::make('formula'),
        TextColumn::make('amount'),
    ];

    $output = captureStreamedResponse((new CsvExporter)->export($query, $columns, 'test.csv'));

    expect($output)->toContain("'=HYPERLINK")
        ->and($output)->toContain("'@SUM(A1)")
        ->and($output)->toContain("'+dangerous")
        // A real negative number is not quoted.
        ->and($output)->toContain('-42')
        ->and($output)->not->toContain("'-42");
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createMockRecord(array $attributes): Model
{
    $record = Mockery::mock(Model::class);
    foreach ($attributes as $key => $value) {
        $record->shouldReceive('getAttribute')->with($key)->andReturn($value);
    }
    // Support getKey for chunkById
    $record->shouldReceive('getKey')->andReturn($attributes['id'] ?? 1);

    return $record;
}

function createMockQuery(array $records): Builder
{
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('chunkById')->andReturnUsing(function (int $size, Closure $callback) use ($records) {
        if (! empty($records)) {
            $callback(collect($records));
        }

        return true;
    });

    return $query;
}

function captureStreamedResponse(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean() ?: '';
}

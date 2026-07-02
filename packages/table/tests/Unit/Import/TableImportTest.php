<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Import\ImportColumn;
use NyonCode\WireTable\Import\TableImport;

class ImportTestContact extends Model
{
    protected $table = 'import_test_contacts';

    protected $guarded = [];

    public $timestamps = false;
}

function tableImportTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'wire-tableimport-');
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    Schema::create('import_test_contacts', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->integer('age')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('import_test_contacts');

    foreach (glob(sys_get_temp_dir().'/wire-tableimport-*') ?: [] as $path) {
        @unlink($path);
    }
});

test('it imports rows into the model', function () {
    $path = tableImportTempCsv("name,email\nJohn,john@example.com\nJane,jane@example.com\n");

    $result = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name')->requiredMapping()->rules(['required']),
            ImportColumn::make('email')->rules(['nullable', 'email']),
        ])
        ->import($path);

    expect($result->getImported())->toBe(2)
        ->and($result->hasFailures())->toBeFalse()
        ->and(ImportTestContact::count())->toBe(2)
        ->and(ImportTestContact::where('email', 'john@example.com')->exists())->toBeTrue();
});

test('it collects per-row validation failures without aborting valid rows', function () {
    $path = tableImportTempCsv("name,email\nJohn,not-an-email\nJane,jane@example.com\n");

    $result = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name')->rules(['required']),
            ImportColumn::make('email')->rules(['required', 'email']),
        ])
        ->import($path);

    expect($result->getImported())->toBe(1)
        ->and($result->getFailedCount())->toBe(1)
        ->and($result->getFailures()[0]['row'])->toBe(1)
        ->and(ImportTestContact::count())->toBe(1)
        ->and(ImportTestContact::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('validation messages use the column label as the attribute name', function () {
    $path = tableImportTempCsv("name,email\n,x@example.com\n");

    $result = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name')->label('Full name')->rules(['required']),
        ])
        ->import($path);

    expect($result->getFailures()[0]['errors'][0])->toContain('Full name');
});

test('castStateUsing transforms the raw cell value before persisting', function () {
    $path = tableImportTempCsv("name,age\nJohn,42\n");

    TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name'),
            ImportColumn::make('age')->castStateUsing(fn ($value) => (int) $value),
        ])
        ->import($path);

    expect(ImportTestContact::first()->age)->toBe(42);
});

test('updateExisting updates matched rows and creates new ones', function () {
    ImportTestContact::create(['name' => 'Old name', 'email' => 'john@example.com']);

    $path = tableImportTempCsv("name,email\nJohn Updated,john@example.com\nJane,jane@example.com\n");

    $result = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name'),
            ImportColumn::make('email'),
        ])
        ->updateExisting(['email'])
        ->import($path);

    expect($result->getImported())->toBe(2)
        ->and(ImportTestContact::count())->toBe(2)
        ->and(ImportTestContact::where('email', 'john@example.com')->value('name'))->toBe('John Updated');
});

test('updateExisting with an unmapped match attribute fails fast instead of overwriting rows (regression: empty updateOrCreate key matched the first record)', function () {
    ImportTestContact::create(['name' => 'Existing A', 'email' => 'a@example.com']);
    ImportTestContact::create(['name' => 'Existing B', 'email' => 'b@example.com']);

    // The file has no "email" header, so the match column stays unmapped.
    $path = tableImportTempCsv("name\nImported One\nImported Two\n");

    $import = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name')->requiredMapping(),
            ImportColumn::make('email'),
        ])
        ->updateExisting(['email']);

    expect(fn () => $import->import($path))
        ->toThrow(RuntimeException::class, 'updateExisting() attribute(s) [email]');

    // Nothing was persisted or overwritten.
    expect(ImportTestContact::orderBy('id')->pluck('name')->all())->toBe(['Existing A', 'Existing B']);
});

test('updateExisting with an attribute that has no import column at all fails fast', function () {
    $path = tableImportTempCsv("name\nJohn\n");

    $import = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([ImportColumn::make('name')])
        ->updateExisting(['external_id']);

    expect(fn () => $import->import($path))
        ->toThrow(RuntimeException::class, 'updateExisting() attribute(s) [external_id]');
});

test('createUsing runs a custom persistence handler', function () {
    $path = tableImportTempCsv("name,email\nJohn,john@example.com\n");

    $captured = [];

    $result = TableImport::make()
        ->columns([
            ImportColumn::make('name'),
            ImportColumn::make('email'),
        ])
        ->createUsing(function (array $data) use (&$captured) {
            $captured = $data;
        })
        ->import($path);

    expect($result->getImported())->toBe(1)
        ->and($captured)->toBe(['name' => 'John', 'email' => 'john@example.com'])
        ->and(ImportTestContact::count())->toBe(0);
});

test('a required column missing from the file throws', function () {
    $path = tableImportTempCsv("name\nJohn\n");

    $import = TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name'),
            ImportColumn::make('email')->label('Email address')->requiredMapping(),
        ]);

    expect(fn () => $import->import($path))
        ->toThrow(RuntimeException::class, 'Email address');
});

test('optional columns absent from the file are simply not mapped', function () {
    $path = tableImportTempCsv("name\nJohn\n");

    TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name'),
            ImportColumn::make('email'),
            ImportColumn::make('age'),
        ])
        ->import($path);

    $contact = ImportTestContact::first();

    expect($contact->name)->toBe('John')
        ->and($contact->email)->toBeNull();
});

test('importing without a model or createUsing handler throws', function () {
    $path = tableImportTempCsv("name\nJohn\n");

    $import = TableImport::make()->columns([ImportColumn::make('name')]);

    expect(fn () => $import->import($path))
        ->toThrow(RuntimeException::class, 'requires a model');
});

test('guess aliases map alternative headers end-to-end', function () {
    $path = tableImportTempCsv("Full Name,E-Mail\nJohn,john@example.com\n");

    TableImport::make()
        ->model(ImportTestContact::class)
        ->columns([
            ImportColumn::make('name')->guess(['full name']),
            ImportColumn::make('email')->guess(['e-mail']),
        ])
        ->import($path);

    expect(ImportTestContact::where('email', 'john@example.com')->value('name'))->toBe('John');
});

test('getters expose the configured columns and update-existing keys', function () {
    $import = TableImport::make()
        ->columns([ImportColumn::make('name')])
        ->delimiter(';')
        ->enclosure("'")
        ->updateExisting(['email']);

    expect($import->getColumns())->toHaveCount(1)
        ->and($import->getDelimiter())->toBe(';')
        ->and($import->getUpdateExisting())->toBe(['email']);
});

test('a custom enclosure is honoured when parsing', function () {
    $path = tableImportTempCsv("name,note\n'John, Jr.','hi'\n");

    TableImport::make()
        ->model(ImportTestContact::class)
        ->enclosure("'")
        ->columns([ImportColumn::make('name')])
        ->import($path);

    expect(ImportTestContact::first()->name)->toBe('John, Jr.');
});

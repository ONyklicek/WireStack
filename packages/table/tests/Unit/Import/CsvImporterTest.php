<?php

declare(strict_types=1);

use NyonCode\WireTable\Import\CsvImporter;

/**
 * Write CSV content to a temp file and return its path (auto-cleaned afterEach).
 */
function importTempCsv(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'wire-csv-import-');
    file_put_contents($path, $content);

    return $path;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/wire-csv-import-*') ?: [] as $path) {
        @unlink($path);
    }
});

test('it yields header-keyed rows', function () {
    $path = importTempCsv("name,email\nJohn,john@example.com\nJane,jane@example.com\n");

    $rows = iterator_to_array((new CsvImporter)->rows($path));

    expect($rows)->toBe([
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
    ]);
});

test('it strips a UTF-8 BOM from the first header', function () {
    $path = importTempCsv("\xEF\xBB\xBFname,email\nJohn,john@example.com\n");

    $rows = iterator_to_array((new CsvImporter)->rows($path));

    expect($rows[0])->toHaveKey('name')
        ->and($rows[0]['name'])->toBe('John');
});

test('it trims header whitespace', function () {
    $path = importTempCsv(" name , email \nJohn,x\n");

    $rows = iterator_to_array((new CsvImporter)->rows($path));

    expect(array_keys($rows[0]))->toBe(['name', 'email']);
});

test('it pads short rows and truncates long rows to the header width', function () {
    $path = importTempCsv("a,b\n1\n1,2,3\n");

    $rows = iterator_to_array((new CsvImporter)->rows($path));

    expect($rows[0])->toBe(['a' => '1', 'b' => ''])
        ->and($rows[1])->toBe(['a' => '1', 'b' => '2']);
});

test('it skips blank lines', function () {
    $path = importTempCsv("a,b\n1,2\n\n3,4\n");

    $rows = iterator_to_array((new CsvImporter)->rows($path));

    expect($rows)->toHaveCount(2)
        ->and($rows[1])->toBe(['a' => '3', 'b' => '4']);
});

test('an empty file yields no rows', function () {
    $path = importTempCsv('');

    expect(iterator_to_array((new CsvImporter)->rows($path)))->toBe([]);
});

test('a header-only file yields no rows', function () {
    $path = importTempCsv("a,b\n");

    expect(iterator_to_array((new CsvImporter)->rows($path)))->toBe([]);
});

test('it honours a custom delimiter', function () {
    $path = importTempCsv("a;b\n1;2\n");

    $rows = iterator_to_array((new CsvImporter(delimiter: ';'))->rows($path));

    expect($rows[0])->toBe(['a' => '1', 'b' => '2']);
});

test('rows() yields nothing for an unreadable path', function () {
    expect(iterator_to_array((new CsvImporter)->rows('/no/such/file-'.uniqid().'.csv')))->toBe([]);
});

<?php

declare(strict_types=1);

use NyonCode\WireTable\Import\ImportResult;

test('a fresh result is empty', function () {
    $result = new ImportResult;

    expect($result->getImported())->toBe(0)
        ->and($result->getFailedCount())->toBe(0)
        ->and($result->getTotal())->toBe(0)
        ->and($result->hasFailures())->toBeFalse()
        ->and($result->getFailures())->toBe([]);
});

test('it tracks imported and failed rows', function () {
    $result = new ImportResult;
    $result->addImported();
    $result->addImported();
    $result->addFailure(3, ['The name field is required.']);

    expect($result->getImported())->toBe(2)
        ->and($result->getFailedCount())->toBe(1)
        ->and($result->getTotal())->toBe(3)
        ->and($result->hasFailures())->toBeTrue()
        ->and($result->getFailures())->toBe([
            ['row' => 3, 'errors' => ['The name field is required.']],
        ]);
});

<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Support\DriverDetector;

it('detects mysql driver', function () {
    expect(DriverDetector::isMysql('mysql'))->toBeTrue()
        ->and(DriverDetector::isMysql('mariadb'))->toBeTrue()
        ->and(DriverDetector::isMysql('pgsql'))->toBeFalse();
});

it('detects postgres driver', function () {
    expect(DriverDetector::isPostgres('pgsql'))->toBeTrue()
        ->and(DriverDetector::isPostgres('mysql'))->toBeFalse();
});

it('detects sqlite driver', function () {
    expect(DriverDetector::isSqlite('sqlite'))->toBeTrue()
        ->and(DriverDetector::isSqlite('mysql'))->toBeFalse();
});

<?php

declare(strict_types=1);

use NyonCode\Wire\Tests\TestCase;
use NyonCode\WireBoost\Tests\TestCase as BoostTestCase;
use NyonCode\WireCore\Tests\TestCase as CoreTestCase;
use NyonCode\WireForms\Tests\TestCase as FormsTestCase;
use NyonCode\WireSortable\Tests\TestCase as SortableTestCase;
use NyonCode\WireTable\Tests\TestCase as TableTestCase;

uses(TestCase::class)->in(__DIR__.'/Integration');

uses(SortableTestCase::class)->in(
    __DIR__.'/../packages/sortable/tests/Feature',
);

uses(CoreTestCase::class)->in(
    __DIR__.'/../packages/core/tests/Unit',
    __DIR__.'/../packages/core/tests/Feature',
);

uses(FormsTestCase::class)->in(
    __DIR__.'/../packages/forms/tests/Unit',
    __DIR__.'/../packages/forms/tests/Feature',
    __DIR__.'/../packages/forms/tests/Standalone',
);

uses(TableTestCase::class)->in(
    __DIR__.'/../packages/table/tests/Unit',
    __DIR__.'/../packages/table/tests/Feature',
);

uses(BoostTestCase::class)->in(
    __DIR__.'/../packages/boost/tests/Unit',
    __DIR__.'/../packages/boost/tests/Feature',
);

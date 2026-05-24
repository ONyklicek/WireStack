<?php

declare(strict_types=1);

use NyonCode\Wire\Tests\TestCase;
use NyonCode\WireCore\Tests\TestCase as CoreTestCase;
use NyonCode\WireForms\Tests\TestCase as FormsTestCase;
use NyonCode\WireTable\Tests\TestCase as TableTestCase;

pest()->in(
    __DIR__.'/../packages/core/tests',
    __DIR__.'/../packages/forms/tests',
    __DIR__.'/../packages/table/tests',
    __DIR__.'/../packages/sortable/tests',
    __DIR__.'/Integration',
);

uses(TestCase::class)->in(__DIR__.'/Integration');

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
